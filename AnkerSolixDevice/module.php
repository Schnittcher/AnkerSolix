<?php

class AnkerSolixDevice extends IPSModule
{
    // Einheitendefinitionen für MaintainVariable-Aufrufe
    private const WATT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' W',   'DIGITS' => 0];
    private const KWH  = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' kWh', 'DIGITS' => 2];
    private const PCT  = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %',   'DIGITS' => 0];
    private const VOLT = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' V',   'DIGITS' => 1];
    private const AMP  = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' A',   'DIGITS' => 2];

    // Registriert Properties, Attribute und den Update-Timer beim Anlegen der Instanz
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

    // Legt gerätetypspezifische Variablen an und startet den Timer nach Konfigurationsänderung
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

    // Ruft die aktuellen Messwerte vom IO-Modul ab und schreibt sie in die Variablen
    public function UpdateData(): void
    {
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        try {
            $scene = $this->RequestIO('GetSceneInfo', ['SiteId' => $this->ReadPropertyString('SiteId')]);
            $this->SendDebug('UpdateData', json_encode($scene, JSON_UNESCAPED_UNICODE), 0);
            $this->ProcessScene($scene);
            $this->SetStatus(102);
        } catch (Exception $e) {
            $this->LogMessage('Anker Solix UpdateData: ' . $e->getMessage(), KL_ERROR);
            $this->SetStatus(203);
        }
    }

    // Schreibt die vollständige API-Antwort (Scene + Device-Objekt) ins Nachrichtenarchiv zur Diagnose
    public function DebugData(): void
    {
        try {
            $scene = $this->RequestIO('GetSceneInfo', ['SiteId' => $this->ReadPropertyString('SiteId')]);
            $this->LogMessage('AnkerSolix Debug Scene: ' . json_encode($scene, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), KL_MESSAGE);

            $type    = $this->ReadPropertyString('DeviceType');
            // smart_meter liegt in grid_info.grid_list, alle anderen im erwarteten *_info.*_list Schema
            // smart_meter liegt in grid_info.grid_list; smartplug_list ohne Unterstrich (API-Inkonsistenz)
            $infoKey = ['solarbank' => 'solarbank_info', 'smartplug' => 'smart_plug_info', 'smart_meter' => 'grid_info', 'pps' => 'pps_info', 'home_power' => 'home_info'][$type] ?? '';
            $listKey = ['solarbank' => 'solarbank_list', 'smartplug' => 'smartplug_list',  'smart_meter' => 'grid_list', 'pps' => 'pps_list', 'home_power' => 'home_device_list'][$type] ?? '';
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

    // Lädt form.json und injiziert das Gerätebild als Base64-Data-URI in den Platzhalter __deviceImage__
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

        if ($base64 === '') {
            // Bild-Element aus Form entfernen wenn kein Bild vorhanden
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

    // Holt die Bild-URL des Geräts aus der Scene-API und speichert sie im Attribut
    private function FetchDeviceImageFromScene(): void
    {
        try {
            $scene = $this->RequestIO('GetSceneInfo', ['SiteId' => $this->ReadPropertyString('SiteId')]);
            $info  = $scene['solarbank_info'] ?? $scene['smart_plug_info'] ?? $scene['pps_info'] ?? $scene['home_info'] ?? $scene['grid_info'] ?? [];
            $sn    = $this->ReadPropertyString('DeviceSn');
            foreach (['solarbank_list', 'smartplug_list', 'pps_list', 'home_device_list', 'grid_list'] as $listKey) {
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

    // Lädt das Gerätebild per HTTP, kodiert es als Base64-Data-URI und cached es im Attribut
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

    // Legt nur die für den Gerätetyp relevanten Variablen an bzw. entfernt nicht benötigte
    private function MaintainVariablesForType(string $type): void
    {
        $isSolarbank  = in_array($type, ['solarbank', 'pps', 'home_power']);
        $hasBattery   = in_array($type, ['solarbank', 'pps', 'home_power']);
        $hasSolar     = in_array($type, ['solarbank', 'home_power']);
        $hasPower     = in_array($type, ['smartplug', 'smart_meter', 'pps']);
        $hasVoltCurr  = in_array($type, ['pps', 'smart_meter']);          // Smart Plug liefert keine V/A
        $hasTotalPlug = in_array($type, ['pps', 'smart_meter']);          // Smart Plug liefert keine kWh
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

        $this->MaintainVariable('TotalEnergy', $this->Translate('Total Energy'), VARIABLETYPE_FLOAT, self::KWH, 75, $isSolarbank || $hasTotalPlug);

        $this->MaintainVariable('Power',       $this->Translate('Power'),   VARIABLETYPE_FLOAT,   self::WATT, 80, $hasPower);
        $this->MaintainVariable('Voltage',     $this->Translate('Voltage'), VARIABLETYPE_FLOAT,   self::VOLT, 90, $hasVoltCurr);
        $this->MaintainVariable('Current',     $this->Translate('Current'), VARIABLETYPE_FLOAT,   self::AMP,  100, $hasVoltCurr);
    }

    // ── Scene-Verarbeitung ───────────────────────────────────────────────────────

    // Leitet die API-Antwort je nach Gerätetyp an die passende Verarbeitungsmethode weiter
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

    // Verarbeitet die Scene-Daten einer Solarbank und befüllt alle Variablen
    private function ProcessSolarbank(array $scene): void
    {
        $info   = $scene['solarbank_info'] ?? [];
        $device = $this->FindDevice($info['solarbank_list'] ?? []);
        if ($device === null) {
            $this->SendDebug('ProcessSolarbank', 'Kein Gerät gefunden (solarbank_list leer oder SN nicht gefunden)', 0);
            return;
        }
        $this->SendDebug('ProcessSolarbank', 'Gerät: ' . ($device['device_sn'] ?? '?') . ' / ' . ($device['device_name'] ?? '?'), 0);

        $soc        = (float)($device['battery_power'] ?? $device['soc'] ?? 0);
        $solar      = (float)($device['photovoltaic_power'] ?? $info['total_photovoltaic_power'] ?? 0);
        $output     = (float)($device['output_power'] ?? $info['total_output_power'] ?? 0);
        $homeLoad   = (float)($scene['home_load_power'] ?? $info['to_home_load'] ?? 0);

        $this->SetValue('SOC',         $soc);
        $this->SetValue('SolarPower',  $solar);
        $this->SetValue('OutputPower', $output);
        $this->SetValue('HomeLoad',    $homeLoad);
        $this->SendDebug('ProcessSolarbank', "SOC=$soc% | Solar=$solar W | Output=$output W | HomeLoad=$homeLoad W", 0);

        // charging_status: 1=nur Laden, 2=Entladen, 3=Solar aktiv (bat_charge_power direkt verwertbar)
        // Bei Status 2 enthält charging_power die Entladeleistung; bei 1/3 die Ladeleistung
        $chargingStatus = (int)($device['charging_status'] ?? 0);
        $chargingPower  = (float)($device['charging_power'] ?? 0);
        $batCharge      = (float)($device['bat_charge_power']    ?? 0);
        $batDischarge   = (float)($device['bat_discharge_power'] ?? 0);
        $this->SendDebug('ProcessSolarbank', "charging_status=$chargingStatus | charging_power=$chargingPower | bat_charge=$batCharge | bat_discharge=$batDischarge", 0);

        if ($chargingStatus === 2) {
            $charge    = 0.0;
            $discharge = $chargingPower;
        } elseif ($chargingStatus === 3) {
            $charge    = $batCharge;
            $discharge = $batDischarge;
        } else {
            // Status 1 oder 0
            $charge    = $chargingPower > 0 ? $chargingPower : $batCharge;
            $discharge = 0.0;
        }

        $this->SetValue('BatteryPower',   $charge > 0 ? $charge : -$discharge);
        $this->SetValue('Chargepower',    $charge);
        $this->SetValue('Dischargepower', $discharge);
        $this->SendDebug('ProcessSolarbank', "Charge=$charge W | Discharge=$discharge W", 0);

        // retain_load enthält Einspeisevorgabe als String z.B. "130W"
        $retainLoad = preg_replace('/[^0-9.]/', '', $scene['retain_load'] ?? '');
        $gridExport = $retainLoad !== '' ? (float)$retainLoad : (float)($info['total_output_power'] ?? 0);
        $this->SetValue('GridExport', $gridExport);

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
        $this->SendDebug('ProcessSolarbank', "Status=$status | GridExport=$gridExport W", 0);

        // Batterieenergie aus SOC und nominaler Kapazität berechnen (API liefert keinen Wh-Wert)
        $nominalKwh  = $this->GuessCapacityKwh($device['device_pn'] ?? '');
        $battEnergy  = round($nominalKwh * $soc / 100, 2);
        $this->SetValue('BatteryEnergy', $battEnergy);

        // Gesamtenergie aus statistics-Array (type=1 entspricht kWh-Gesamtertrag)
        $totalEnergy = 0.0;
        foreach ($scene['statistics'] ?? [] as $stat) {
            if (($stat['type'] ?? '') === '1') { $totalEnergy = (float)$stat['total']; break; }
        }
        $this->SetValue('TotalEnergy', $totalEnergy);
        $this->SendDebug('ProcessSolarbank', "BatteryEnergy=$battEnergy kWh | TotalEnergy=$totalEnergy kWh", 0);
    }

    // Verarbeitet die Scene-Daten eines Smart Plugs
    private function ProcessSmartPlug(array $scene): void
    {
        $info   = $scene['smart_plug_info'] ?? [];
        // API verwendet smartplug_list (ohne Unterstrich), nicht smart_plug_list
        $device = $this->FindDevice($info['smartplug_list'] ?? []);
        if ($device === null) {
            $this->SendDebug('ProcessSmartPlug', 'Kein Gerät gefunden (smartplug_list leer oder SN nicht gefunden)', 0);
            return;
        }
        $this->SendDebug('ProcessSmartPlug', 'Gerät: ' . ($device['device_sn'] ?? '?') . ' / ' . ($device['device_name'] ?? '?'), 0);

        // current_power = aktueller Verbrauch in W; status "1" = Gerät online (kein Schaltzustand)
        // API liefert pro Plug nur current_power und status — Spannung/Strom/Energie nicht verfügbar
        $power = (float)($this->FindKey($device, ['current_power', 'power', 'current_power_w']) ?? 0);
        $sw    = (bool)($device['switch_status'] ?? ($device['status'] ?? '0') === '1');

        $this->SetValue('Power', $power);
        $this->SendDebug('ProcessSmartPlug', "Power=$power W", 0);
    }

    // Verarbeitet die Scene-Daten eines Smart Meters (liegt in grid_info.grid_list)
    private function ProcessSmartMeter(array $scene): void
    {
        $info   = $scene['grid_info'] ?? [];
        $device = $this->FindDevice($info['grid_list'] ?? [])
                  ?? (isset($info['device_sn']) ? $info : null);
        if ($device === null) {
            $this->SendDebug('ProcessSmartMeter', 'Kein Gerät gefunden (grid_list leer oder SN nicht gefunden)', 0);
            return;
        }
        $this->SendDebug('ProcessSmartMeter', 'Gerät: ' . ($device['device_sn'] ?? '?') . ' / ' . ($device['device_name'] ?? '?'), 0);

        // Netzleistung: positiv = Bezug vom Netz, negativ = Einspeisung ins Netz
        // photovoltaic_to_grid_power kann negativ sein (API-Vorzeichen = Einspeisung positiv)
        $toHome   = (float)($info['grid_to_home_power']         ?? 0);
        $toGrid   = (float)($info['photovoltaic_to_grid_power'] ?? 0);
        $netPower = $toHome - $toGrid;

        $this->SetValue('Power',       $netPower);
        $this->SetValue('Voltage',     (float)($device['voltage'] ?? 0));
        $this->SetValue('Current',     (float)($device['current'] ?? 0));
        $this->SetValue('TotalEnergy', (float)($this->FindKey($device, ['total_energy', 'total_energy_kwh']) ?? 0));
        $this->SendDebug('ProcessSmartMeter', "grid_to_home=$toHome W | pv_to_grid=$toGrid W | NetPower=$netPower W", 0);
    }

    // Verarbeitet die Scene-Daten einer Powerstation (PPS)
    private function ProcessPPS(array $scene): void
    {
        $info   = $scene['pps_info'] ?? [];
        $device = $this->FindDevice($info['pps_list'] ?? []);
        if ($device === null) {
            $this->SendDebug('ProcessPPS', 'Kein Gerät gefunden (pps_list leer oder SN nicht gefunden)', 0);
            return;
        }
        $this->SendDebug('ProcessPPS', 'Gerät: ' . ($device['device_sn'] ?? '?') . ' / ' . ($device['device_name'] ?? '?'), 0);

        $soc    = (float)($device['battery_power'] ?? $device['soc'] ?? 0);
        $output = (float)($this->FindKey($device, ['output_power', 'ac_out_power']) ?? 0);
        $power  = (float)($this->FindKey($device, ['input_power', 'charging_power']) ?? 0);

        $this->SetValue('SOC',           $soc);
        $this->SetValue('BatteryEnergy', (float)($this->FindKey($device, ['battery_energy', 'battery_energy_wh']) ?? 0) / 1000);
        $this->SetValue('OutputPower',   $output);
        $this->SetValue('Power',         $power);
        $this->SetValue('Voltage',       (float)($device['voltage'] ?? 0));
        $this->SetValue('Current',       (float)($device['current'] ?? 0));
        $this->SetValue('TotalEnergy',   (float)($this->FindKey($device, ['total_energy', 'total_energy_kwh']) ?? 0));
        $this->SendDebug('ProcessPPS', "SOC=$soc% | Output=$output W | Input=$power W", 0);
    }

    // Verarbeitet die Scene-Daten einer Home Power Station
    private function ProcessHomePower(array $scene): void
    {
        $info   = $scene['home_info'] ?? [];
        $device = $this->FindDevice($info['home_device_list'] ?? [])
                  ?? (isset($info['device_sn']) ? $info : null);
        if ($device === null) {
            $this->SendDebug('ProcessHomePower', 'Kein Gerät gefunden (home_device_list leer oder SN nicht gefunden)', 0);
            return;
        }
        $this->SendDebug('ProcessHomePower', 'Gerät: ' . ($device['device_sn'] ?? '?') . ' / ' . ($device['device_name'] ?? '?'), 0);

        $soc      = (float)($device['battery_power'] ?? $device['soc'] ?? 0);
        $solar    = (float)($this->FindKey($device, ['solar_power_w', 'pv_power']) ?? 0);
        $this->SetValue('SOC',        $soc);
        $this->SetValue('SolarPower', $solar);

        $hpCharge    = (float)($this->FindKey($device, ['charging_power', 'bat_charge_power']) ?? 0);
        $hpDischarge = (float)($this->FindKey($device, ['discharging_power', 'bat_discharge_power']) ?? 0);
        $this->SetValue('BatteryPower',   $hpCharge > 0 ? $hpCharge : -$hpDischarge);
        $this->SetValue('Chargepower',    $hpCharge);
        $this->SetValue('Dischargepower', $hpDischarge);
        $this->SendDebug('ProcessHomePower', "SOC=$soc% | Solar=$solar W | Charge=$hpCharge W | Discharge=$hpDischarge W", 0);

        $hpOutput = (float)($this->FindKey($device, ['output_power', 'ac_out_power']) ?? 0);
        $hpGrid   = (float)($this->FindKey($device, ['output_home_load', 'grid_to_home_load']) ?? $hpOutput);
        $hpHome   = (float)($this->FindKey($scene,  ['home_load_power', 'load_power_w']) ?? 0);
        $this->SetValue('OutputPower', $hpOutput);
        $this->SetValue('GridExport',  $hpGrid);
        $this->SetValue('HomeLoad',    $hpHome);

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
        $this->SendDebug('ProcessHomePower', "Output=$hpOutput W | Grid=$hpGrid W | HomeLoad=$hpHome W | Status=$hpStatus", 0);
    }

    // Gibt die nominale Kapazität in kWh anhand der Produktnummer zurück (API liefert keinen Wh-Wert)
    private function GuessCapacityKwh(string $pn): float
    {
        return ['A17C0' => 1.6, 'A17C1' => 1.6, 'A17C2' => 3.2, 'A17B1' => 0.768][$pn] ?? 1.6;
    }

    // ── Hilfsmethoden ───────────────────────────────────────────────────────────

    // Sucht das Gerät mit der konfigurierten Seriennummer in der Geräteliste; fällt auf erstes Gerät zurück
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

    // Sendet eine Anfrage an das übergeordnete IO-Modul und gibt die dekodierte Antwort zurück
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

    // Gibt den ersten gesetzten Wert aus einer Liste möglicher Feldnamen zurück (API-Feldname-Varianten)
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
