<?php

class AnkerSolixKonfigurator extends IPSModule
{
    // Gerätetypen: info-Key in der Scene → Listen-Key → Typ-Label
    private const DEVICE_TYPES = [
        'solarbank'   => ['info' => 'solarbank_info',   'list' => 'solarbank_list',   'label' => 'Solarbank'],
        'smartplug'   => ['info' => 'smartplug_info',   'list' => 'smartplug_list',   'label' => 'Smart Plug'],
        'smart_meter' => ['info' => 'smart_meter_info', 'list' => 'smart_meter_list', 'label' => 'Smart Meter'],
        'pps'         => ['info' => 'pps_info',         'list' => 'pps_list',         'label' => 'Powerstation'],
        'home_power'  => ['info' => 'home_info',        'list' => 'home_device_list', 'label' => 'Home Power'],
    ];

    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{8570691A-D27F-5BD4-1628-CBE518652227}');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetStatus(102);
    }

    public function ReceiveData($JSONString): void
    {
        // Kein Push vom IO-Modul erwartet
    }

    public function GetConfigurationForm(): string
    {
        $form   = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $values = [];

        try {
                $sites = $this->RequestIO('GetSiteList', []);

                foreach ($sites as $site) {
                    $siteId   = $site['site_id']  ?? '';
                    $siteName = $site['site_name'] ?? $siteId;
                    $scene    = $this->RequestIO('GetSceneInfo', ['SiteId' => $siteId]);

                    $foundAny = false;
                    foreach (self::DEVICE_TYPES as $typeKey => $typeDef) {
                        $infoBlock  = $scene[$typeDef['info']] ?? [];
                        $deviceList = $infoBlock[$typeDef['list']] ?? [];

                        // Manche Typen liefern das Gerät direkt im info-Block (kein Unter-Array)
                        if (empty($deviceList) && !empty($infoBlock) && isset($infoBlock['device_sn'])) {
                            $deviceList = [$infoBlock];
                        }

                        foreach ($deviceList as $device) {
                            $deviceSn   = $device['device_sn']   ?? '';
                            $deviceName = $device['device_name'] ?? $device['alias_name'] ?? $typeDef['label'];
                            $values[]   = $this->BuildRow($siteName, $deviceName, $typeDef['label'], $deviceSn, $siteId, $typeKey);
                            $foundAny   = true;
                        }
                    }

                    if (!$foundAny) {
                        $values[] = $this->BuildRow($siteName, 'Keine Geräte', '-', '', $siteId, '');
                    }
                }
        } catch (Exception $e) {
            $this->LogMessage('Anker Solix Konfigurator: ' . $e->getMessage(), KL_ERROR);
        }

        foreach ($form['elements'] as &$element) {
            if (($element['type'] ?? '') === 'Configurator') {
                $element['values'] = $values;
            }
        }

        return json_encode($form);
    }

    // ── Private ─────────────────────────────────────────────────────────────────

    private function RequestIO(string $action, array $params): array
    {
        $payload           = $params;
        $payload['DataID'] = '{56367DF0-FB25-4588-9E60-C19AFB148E76}';
        $payload['Action'] = $action;

        $result = $this->SendDataToParent(json_encode($payload));

        if ($result === false || $result === '') {
            throw new Exception('Kein IO-Modul verbunden');
        }

        $data = json_decode($result, true);
        if (isset($data['error'])) {
            throw new Exception($data['error']);
        }

        return is_array($data) ? $data : [];
    }

    private function BuildRow(string $siteName, string $deviceName, string $typeLabel, string $deviceSn, string $siteId, string $typeKey): array
    {
        $instanceID = $this->FindExistingInstance($siteId, $deviceSn, $typeKey);

        return [
            'SiteName'   => $siteName,
            'DeviceType' => $typeLabel,
            'DeviceName' => $deviceName,
            'DeviceSn'   => $deviceSn,
            'instanceID' => $instanceID,
            'create'     => [
                'moduleID'      => '{660A99F2-398D-2B4D-9A1B-534DC3C70004}',
                'configuration' => [
                    'SiteId'     => $siteId,
                    'DeviceSn'   => $deviceSn,
                    'DeviceName' => $deviceName,
                    'DeviceType' => $typeKey,
                ],
            ],
        ];
    }

    private function FindExistingInstance(string $siteId, string $deviceSn, string $typeKey): int
    {
        foreach (IPS_GetInstanceListByModuleID('{660A99F2-398D-2B4D-9A1B-534DC3C70004}') as $id) {
            if (IPS_GetProperty($id, 'SiteId') === $siteId
                && IPS_GetProperty($id, 'DeviceSn') === $deviceSn
                && IPS_GetProperty($id, 'DeviceType') === $typeKey) {
                return $id;
            }
        }
        return 0;
    }
}
