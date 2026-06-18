<?php

class AnkerSolixDevice extends IPSModule
{
    private const WATT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' W',   'DIGITS' => 0];
    private const KWH  = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' kWh', 'DIGITS' => 2];
    private const PCT  = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %',   'DIGITS' => 0];
    private const VOLT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' V',   'DIGITS' => 1];
    private const AMP  = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' A',   'DIGITS' => 2];

    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{8570691A-D27F-5BD4-1628-CBE518652227}');

        $this->RegisterPropertyString('SiteId','');
        $this->RegisterPropertyString('DeviceSn','');
        $this->RegisterPropertyString('DeviceName','');
        $this->RegisterPropertyString('DeviceType','solarbank');
        $this->RegisterPropertyInteger('UpdateInterval', 60);

        $this->RegisterAttributeString('DeviceImageBase64', '');
        $this->RegisterAttributeString('DeviceImageUrl','');

        $this->RegisterTimer('UpdateTimer', 0, 'ANKERSOLIX_UpdateData($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if ($this->ReadPropertyString('SiteId') === '') {
            $this->SetStatus(201);
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        $this->MaintainVariablesForType($this->ReadPropertyString('DeviceType'));
        $this->SetStatus(102);
        $this->SetTimerInterval('UpdateTimer', 1000);
    }

    // ── Public ──────────────────────────────────────────────────────────────────

    public function UpdateData(): void
    {
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        try {
            $scene = $this->RequestIO('GetSceneInfo', ['SiteId' => $this->ReadPropertyString('SiteId')]);
            $this->ProcessScene($scene);
            $this->SetStatus(102);
        } catch (Exception $e) {
            $this->LogMessage('Anker Solix UpdateData: ' . $e->getMessage(), KL_ERROR);
            $this->SetStatus(203);
        }
    }

    public function DebugData(): void
    {
        try {
            $scene = $this->RequestIO('GetSceneInfo', ['SiteId' => $this->ReadPropertyString('SiteId')]);
            $this->LogMessage('AnkerSolix Debug Scene: ' . json_encode($scene, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), KL_MESSAGE);

            $type    = $this->ReadPropertyString('DeviceType');
            $infoKey = ['solarbank' => 'solarbank_info', 'smartplug' => 'smartplug_info', 'smart_meter' => 'smart_meter_info', 'pps' => 'pps_info', 'home_power' => 'home_info'][$type] ?? '';
            $listKey = ['solarbank' => 'solarbank_list', 'smartplug' => 'smartplug_list', 'smart_meter' => 'smart_meter_list', 'pps' => 'pps_list', 'home_power' => 'home_device_list'][$type] ?? '';
            $info    = $scene[$infoKey] ?? [];
            $list    = $info[$listKey] ?? [];
            $sn      = $this->ReadPropertyString('DeviceSn');
            $device  = null;
            foreach ($list as $d) {
                if (($d['device_sn'] ?? '') === $sn) { $device = $d; break; }
            }
            if ($device === null && !empty($list)) $device = $list[0];
            $this->LogMessage('AnkerSolix Debug Device: ' . json_encode($device, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), KL_MESSAGE);

            echo 'Debug-Daten ins Symcon-Nachrichtenarchiv geschrieben.';
        } catch (Exception $e) {
            echo 'Fehler: ' . $e->getMessage();
        }
    }

    public function ReceiveData($JSONString): void
    {
    }

    public function GetConfigurationForm(): string
    {
        $form   = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $base64 = $this->ReadAttributeString('DeviceImageBase64');

        if ($base64 === '') {
            if ($this->ReadAttributeString('DeviceImageUrl') === '') {
                $this->FetchDeviceImageFromScene();
            }
            $base64 = $this->FetchDeviceImage();
        }

        // Bild-Element aus Form entfernen wenn kein Bild vorhanden
        if ($base64 === '') {
            foreach ($form['elements'] as $eIdx => $element) {
                if (($element['type'] ?? '') === 'ExpansionPanel') {
                    foreach ($element['items'] ?? [] as $iIdx => $item) {
                        if (($item['type'] ?? '') === 'Image') {
                            unset($form['elements'][$eIdx]['items'][$iIdx]);
                            $form['elements'][$eIdx]['items'] = array_values($form['elements'][$eIdx]['items']);
                        }
                    }
                }
            }
        } else {
            array_walk_recursive($form, function (&$value) use ($base64) {
                if ($value === '__deviceImage__') {
                    $value = $base64;
                }
            });
        }

        return json_encode($form);
    }

    private function FetchDeviceImageFromScene(): void
    {
        try {
            $scene = $this->RequestIO('GetSceneInfo', ['SiteId' => $this->ReadPropertyString('SiteId')]);
            $info  = $scene['solarbank_info'] ?? $scene['smartplug_info'] ?? $scene['pps_info'] ?? $scene['home_info'] ?? [];
            $sn    = $this->ReadPropertyString('DeviceSn');
            foreach (['solarbank_list', 'smartplug_list', 'pps_list', 'home_device_list', 'smart_meter_list'] as $listKey) {
                foreach ($info[$listKey] ?? [] as $d) {
                    if (($d['device_sn'] ?? '') === $sn && !empty($d['device_img'])) {
                        // URL in Attribut statt Property speichern — kein ApplyChanges nötig
                        $this->WriteAttributeString('DeviceImageUrl', $d['device_img']);
                        return;
                    }
                }
            }
        } catch (Exception $e) {
            // Bild nicht verfügbar
        }
    }

    private function FetchDeviceImage(): string
    {
        $url = $this->ReadAttributeString('DeviceImageUrl');
        if ($url === '') return '';

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
        $data = curl_exec($ch);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($data === false || $data === '') return '';

        $mime   = explode(';', $type)[0] ?? 'image/png';
        $base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
        $this->WriteAttributeString('DeviceImageBase64', $base64);
        return $base64;
    }

    // ── Variablen-Verwaltung ─────────────────────────────────────────────────────

    private function MaintainVariablesForType(string $type): void
    {
        $isSolarbank = in_array($type, ['solarbank', 'pps', 'home_power']);
        $isPlug      = in_array($type, ['smartplug', 'smart_meter', 'pps']);
        $hasBattery  = in_array($type, ['solarbank', 'pps', 'home_power']);
        $hasSolar    = in_array($type, ['solarbank', 'home_power']);
        $hasSwitch   = $type === 'smartplug';

        $this->MaintainVariable('SolarPower',   $this->Translate('Solar Power'),      VARIABLETYPE_FLOAT, self::WATT, 10, $hasSolar);

        $this->MaintainVariable('SOC',           $this->Translate('Battery Level'),    VARIABLETYPE_FLOAT, self::PCT,  20, $hasBattery);
        $this->MaintainVariable('BatteryEnergy', $this->Translate('Battery Energy'),   VARIABLETYPE_FLOAT, self::KWH,  30, $hasBattery);
        $this->MaintainVariable('BatteryPower',  $this->Translate('Battery Power'),    VARIABLETYPE_FLOAT, self::WATT, 40, $hasBattery);
        $this->MaintainVariable('Chargepower',   $this->Translate('Charge Power'),     VARIABLETYPE_FLOAT, self::WATT, 45, $hasBattery);
        $this->MaintainVariable('Dischargepower',$this->Translate('Discharge Power'),  VARIABLETYPE_FLOAT, self::WATT, 46, $hasBattery);

        $this->MaintainVariable('OperatingStatus', $this->Translate('Operating Status'), VARIABLETYPE_STRING, [], 50, $isSolarbank);
        $this->MaintainVariable('OutputPower',     $this->Translate('Output Power'),     VARIABLETYPE_FLOAT, self::WATT, 60, $isSolarbank);
        $this->MaintainVariable('GridExport',      $this->Translate('Grid Export'),      VARIABLETYPE_FLOAT, self::WATT, 65, $isSolarbank);
        $this->MaintainVariable('HomeLoad',        $this->Translate('Home Load'),        VARIABLETYPE_FLOAT, self::WATT, 70, $isSolarbank);

        $this->MaintainVariable('TotalEnergy', $this->Translate('Total Energy'), VARIABLETYPE_FLOAT, self::KWH, 75, true);

        $this->MaintainVariable('Power',       $this->Translate('Power'),        VARIABLETYPE_FLOAT,   self::WATT, 80, $isPlug);
        $this->MaintainVariable('Voltage',     $this->Translate('Voltage'),      VARIABLETYPE_FLOAT,   self::VOLT, 90, $isPlug);
        $this->MaintainVariable('Current',     $this->Translate('Current'),      VARIABLETYPE_FLOAT,   self::AMP,  100, $isPlug);
        $this->MaintainVariable('SwitchState', $this->Translate('Switch'),       VARIABLETYPE_BOOLEAN,
            ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 110, $hasSwitch);
    }

    // ── Scene-Verarbeitung ───────────────────────────────────────────────────────

    private function ProcessScene(array $scene): void
    {
        switch ($this->ReadPropertyString('DeviceType')) {
            case 'solarbank':   $this->ProcessSolarbank($scene);  break;
            case 'smartplug':   $this->ProcessSmartPlug($scene);  break;
            case 'smart_meter': $this->ProcessSmartMeter($scene); break;
            case 'pps':         $this->ProcessPPS($scene);        break;
            case 'home_power':  $this->ProcessHomePower($scene);  break;
        }
    }

    private function ProcessSolarbank(array $scene): void
    {
        $info   = $scene['solarbank_info'] ?? [];
        $device = $this->FindDevice($info['solarbank_list'] ?? []);
        if ($device === null) return;

        $this->SetValue('SOC',          (float)($device['battery_power'] ?? $device['soc'] ?? 0));
        $this->SetValue('SolarPower',   (float)($device['photovoltaic_power'] ?? $info['total_photovoltaic_power'] ?? 0));
        $this->SetValue('OutputPower',  (float)($device['output_power'] ?? $info['total_output_power'] ?? 0));
        $this->SetValue('HomeLoad',     (float)($scene['home_load_power'] ?? $info['to_home_load'] ?? 0));

        // charging_status: 1=Laden, 2=Entladen — charging_power enthält den jeweiligen Wert
        $chargingStatus = (int)($device['charging_status'] ?? 0);
        $chargingPower  = (float)($device['charging_power'] ?? 0);
        $charge    = $chargingStatus === 1 ? $chargingPower : 0.0;
        $discharge = $chargingStatus === 2 ? $chargingPower : 0.0;

        $this->SetValue('BatteryPower',   $charge > 0 ? $charge : -$discharge);
        $this->SetValue('Chargepower',    $charge);
        $this->SetValue('Dischargepower', $discharge);

        // retain_load enthält Einspeisevorgabe als String z.B. "130W"
        $retainLoad = preg_replace('/[^0-9.]/', '', $scene['retain_load'] ?? '');
        $this->SetValue('GridExport', $retainLoad !== '' ? (float)$retainLoad : (float)($info['total_output_power'] ?? 0));

        if ($charge > 0) {
            $status = $this->Translate('Charging');
        } elseif ($discharge > 0) {
            $status = $this->Translate('Discharging');
        } elseif ((float)($device['output_power'] ?? 0) > 0) {
            $status = $this->Translate('Bypass');
        } else {
            $status = $this->Translate('Standby');
        }
        $this->SetValue('OperatingStatus', $status);

        $nominalKwh = $this->GuessCapacityKwh($device['device_pn'] ?? '');
        $this->SetValue('BatteryEnergy', round($nominalKwh * (float)($device['battery_power'] ?? 0) / 100, 2));

        $totalEnergy = 0.0;
        foreach ($scene['statistics'] ?? [] as $stat) {
            if (($stat['type'] ?? '') === '1') { $totalEnergy = (float)$stat['total']; break; }
        }
        $this->SetValue('TotalEnergy', $totalEnergy);


    }

    private function ProcessSmartPlug(array $scene): void
    {
        $info   = $scene['smartplug_info'] ?? [];
        $device = $this->FindDevice($info['smartplug_list'] ?? []);
        if ($device === null) return;

        $this->SetValue('Power',       (float)($this->FindKey($device, ['power', 'current_power_w']) ?? 0));
        $this->SetValue('Voltage',     (float)($device['voltage'] ?? 0));
        $this->SetValue('Current',     (float)($device['current'] ?? 0));
        $this->SetValue('SwitchState', (bool)($device['status'] ?? $device['switch_status'] ?? false));
        $this->SetValue('TotalEnergy', (float)($this->FindKey($device, ['total_energy', 'total_energy_kwh']) ?? 0));

    }

    private function ProcessSmartMeter(array $scene): void
    {
        $info   = $scene['smart_meter_info'] ?? [];
        $device = $this->FindDevice($info['smart_meter_list'] ?? [])
                  ?? (isset($info['device_sn']) ? $info : null);
        if ($device === null) return;

        $this->SetValue('Power',       (float)($this->FindKey($device, ['power', 'grid_power_w', 'current_power']) ?? 0));
        $this->SetValue('Voltage',     (float)($device['voltage'] ?? 0));
        $this->SetValue('Current',     (float)($device['current'] ?? 0));
        $this->SetValue('TotalEnergy', (float)($this->FindKey($device, ['total_energy', 'total_energy_kwh']) ?? 0));

    }

    private function ProcessPPS(array $scene): void
    {
        $info   = $scene['pps_info'] ?? [];
        $device = $this->FindDevice($info['pps_list'] ?? []);
        if ($device === null) return;

        $this->SetValue('SOC',           (float)($device['battery_power'] ?? $device['soc'] ?? 0));
        $this->SetValue('BatteryEnergy', (float)($this->FindKey($device, ['battery_energy', 'battery_energy_wh']) ?? 0) / 1000);
        $this->SetValue('OutputPower',   (float)($this->FindKey($device, ['output_power', 'ac_out_power']) ?? 0));
        $this->SetValue('Power',         (float)($this->FindKey($device, ['input_power', 'charging_power']) ?? 0));
        $this->SetValue('Voltage',       (float)($device['voltage'] ?? 0));
        $this->SetValue('Current',       (float)($device['current'] ?? 0));
        $this->SetValue('TotalEnergy',   (float)($this->FindKey($device, ['total_energy', 'total_energy_kwh']) ?? 0));

    }

    private function ProcessHomePower(array $scene): void
    {
        $info   = $scene['home_info'] ?? [];
        $device = $this->FindDevice($info['home_device_list'] ?? [])
                  ?? (isset($info['device_sn']) ? $info : null);
        if ($device === null) return;

        $this->SetValue('SOC',        (float)($device['battery_power'] ?? $device['soc'] ?? 0));
        $this->SetValue('SolarPower', (float)($this->FindKey($device, ['solar_power_w', 'pv_power']) ?? 0));

        $hpCharge    = (float)($this->FindKey($device, ['charging_power', 'bat_charge_power']) ?? 0);
        $hpDischarge = (float)($this->FindKey($device, ['discharging_power', 'bat_discharge_power']) ?? 0);
        $this->SetValue('BatteryPower',    $hpCharge > 0 ? $hpCharge : -$hpDischarge);
        $this->SetValue('Chargepower',    $hpCharge);
        $this->SetValue('Dischargepower', $hpDischarge);

        $hpOutput = (float)($this->FindKey($device, ['output_power', 'ac_out_power']) ?? 0);
        $this->SetValue('OutputPower', $hpOutput);
        $this->SetValue('GridExport',  (float)($this->FindKey($device, ['output_home_load', 'grid_to_home_load']) ?? $hpOutput));
        $this->SetValue('HomeLoad',    (float)($this->FindKey($scene, ['home_load_power', 'load_power_w']) ?? 0));

        if ($hpCharge > 0) {
            $hpStatus = $this->Translate('Charging');
        } elseif ($hpDischarge > 0) {
            $hpStatus = $this->Translate('Discharging');
        } elseif ($hpOutput > 0) {
            $hpStatus = $this->Translate('Bypass');
        } else {
            $hpStatus = $this->Translate('Standby');
        }
        $this->SetValue('OperatingStatus', $hpStatus);
        $this->SetValue('BatteryEnergy', (float)($this->FindKey($device, ['battery_energy', 'battery_energy_wh']) ?? 0) / 1000);
        $this->SetValue('TotalEnergy',   (float)($this->FindKey($device, ['total_energy', 'total_energy_kwh']) ?? 0));

    }

    private function GuessCapacityKwh(string $pn): float
    {
        return ['A17C0' => 1.6, 'A17C1' => 1.6, 'A17C2' => 3.2, 'A17B1' => 0.768][$pn] ?? 1.6;
    }

    // ── Hilfsmethoden ───────────────────────────────────────────────────────────

    private function FindDevice(array $list): ?array
    {
        if (empty($list)) return null;
        $sn = $this->ReadPropertyString('DeviceSn');
        if ($sn !== '') {
            foreach ($list as $d) {
                if (($d['device_sn'] ?? '') === $sn) return $d;
            }
        }
        return $list[0];
    }

    private function RequestIO(string $action, array $params): array
    {
        $payload           = $params;
        $payload['DataID'] = '{56367DF0-FB25-4588-9E60-C19AFB148E76}';
        $payload['Action'] = $action;

        $result = $this->SendDataToParent(json_encode($payload));
        if ($result === false || $result === '') {
            throw new Exception('Keine Antwort vom IO-Modul');
        }

        $data = json_decode($result, true);
        if (isset($data['error'])) {
            throw new Exception($data['error']);
        }

        return is_array($data) ? $data : [];
    }

    private function FindKey(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                return $data[$key];
            }
        }
        return null;
    }
}
