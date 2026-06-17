<?php

define('ANKERSOLIX_GUID_UP',   '{56367DF0-FB25-4588-9E60-C19AFB148E76}');
define('ANKERSOLIX_GUID_DOWN', '{BAB5A1A2-56DA-4956-82EC-C997174D9AB6}');
define('ANKERSOLIX_API_BASE',       'https://ankerpower-api-eu.anker.com');
define('ANKERSOLIX_SERVER_PUBLIC_KEY', '04c5c00c4f8d1197cc7c3167c52bf7acb054d722f0ef08dcd7e0883236e0d72a3868d9750cb47fa4619248f3d83f0f662671dadc6e2d31c2f41db0161651c7c076');

class AnkerSolixIO extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Country', 'DE');

        $this->RegisterAttributeString('AuthToken', '');
        $this->RegisterAttributeString('UserId', '');
        $this->RegisterAttributeInteger('TokenExpiry', 0);
        $this->RegisterAttributeString('ClientPrivateKey', '');
        $this->RegisterAttributeString('ClientPublicKey', '');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if ($this->ReadPropertyString('Email') === '' || $this->ReadPropertyString('Password') === '') {
            $this->SetStatus(201);
            return;
        }

        $this->SetStatus(102);
    }

    // ── ForwardData — Kindmodule senden Anfragen hoch ───────────────────────────

    public function ForwardData($JSONString): string
    {
        $request = json_decode($JSONString, true);
        $action  = $request['Action'] ?? '';

        try {
            switch ($action) {
                case 'GetSiteList':
                    return json_encode($this->GetSiteList());

                case 'GetSceneInfo':
                    return json_encode($this->GetSceneInfo($request['SiteId'] ?? ''));

                default:
                    return json_encode(['error' => 'Unbekannte Aktion: ' . $action]);
            }
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    // ── Public ──────────────────────────────────────────────────────────────────

    public function TestConnection(): void
    {
        $this->WriteAttributeString('AuthToken', '');
        $this->WriteAttributeInteger('TokenExpiry', 0);
        $this->WriteAttributeString('ClientPrivateKey', '');
        $this->WriteAttributeString('ClientPublicKey', '');

        try {
            $token = $this->Login();
            if ($token !== null) {
                echo 'Verbindung erfolgreich!';
                $this->SetStatus(102);
            }
        } catch (Exception $e) {
            echo 'Fehler: ' . $e->getMessage();
            $this->SetStatus(202);
        }
    }

    // ── API-Methoden ────────────────────────────────────────────────────────────

    public function GetSiteList(): array
    {
        $token    = $this->EnsureAuthenticated();
        $response = $this->ApiRequest('/power_service/v1/site/get_site_list', ['from' => 'solarbank'], $token);
        return $response['data']['site_list'] ?? [];
    }

    public function GetSceneInfo(string $siteId): array
    {
        $token    = $this->EnsureAuthenticated();
        $response = $this->ApiRequest('/power_service/v1/site/get_scen_info', ['site_id' => $siteId], $token);
        return $response['data'] ?? [];
    }

    // ── Authentifizierung ───────────────────────────────────────────────────────

    private function EnsureAuthenticated(): array
    {
        $token  = $this->ReadAttributeString('AuthToken');
        $expiry = $this->ReadAttributeInteger('TokenExpiry');

        if ($token !== '' && time() < $expiry) {
            return ['auth_token' => $token, 'user_id' => $this->ReadAttributeString('UserId')];
        }

        $result = $this->Login();
        if ($result === null) {
            throw new Exception('Authentifizierung fehlgeschlagen');
        }
        return $result;
    }

    private function Login(): ?array
    {
        $email    = $this->ReadPropertyString('Email');
        $password = $this->ReadPropertyString('Password');
        $country  = $this->ReadPropertyString('Country');

        if ($email === '' || $password === '') {
            $this->SetStatus(201);
            return null;
        }

        $payload = [
            'ab'                 => $country,
            'client_secret_info' => ['public_key' => $this->GetClientPublicKeyHex()],
            'enc'                => 0,
            'email'              => $email,
            'password'           => $this->EncryptPassword($password),
            'time_zone'          => $this->GetTimezoneOffsetMs(),
            'transaction'        => (string)(int)(microtime(true) * 1000),
        ];

        $response = $this->ApiRequest('/passport/login', $payload, null);

        if (!isset($response['data']['auth_token'])) {
            $this->LogMessage('Anker Solix Login fehlgeschlagen: ' . json_encode($response), KL_ERROR);
            $this->SetStatus(202);
            return null;
        }

        $authToken = $response['data']['auth_token'];
        $userId    = $response['data']['user_id'] ?? '';

        $this->WriteAttributeString('AuthToken', $authToken);
        $this->WriteAttributeString('UserId', $userId);
        $this->WriteAttributeInteger('TokenExpiry', time() + 82800);
        $this->SetStatus(102);

        return ['auth_token' => $authToken, 'user_id' => $userId];
    }

    // ── Kryptografie ────────────────────────────────────────────────────────────

    private function GetClientPublicKeyHex(): string
    {
        $stored = $this->ReadAttributeString('ClientPublicKey');
        if ($stored !== '') {
            return $stored;
        }

        $config = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];

        // Auf Linux muss openssl.cnf ggf. explizit angegeben werden
        foreach (['/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf', '/usr/local/ssl/openssl.cnf'] as $cnf) {
            if (is_file($cnf)) {
                $config['config'] = $cnf;
                break;
            }
        }

        $key = openssl_pkey_new($config);
        if ($key === false) {
            throw new Exception('EC-Schlüsselgenerierung fehlgeschlagen: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);
        $x       = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y       = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $pubHex  = '04' . bin2hex($x) . bin2hex($y);

        openssl_pkey_export($key, $privPem, null, isset($config['config']) ? ['config' => $config['config']] : []);
        $this->WriteAttributeString('ClientPrivateKey', $privPem);
        $this->WriteAttributeString('ClientPublicKey', $pubHex);

        return $pubHex;
    }

    private function EncryptPassword(string $password): string
    {
        $privPem = $this->ReadAttributeString('ClientPrivateKey');
        if ($privPem === '') {
            $this->GetClientPublicKeyHex();
            $privPem = $this->ReadAttributeString('ClientPrivateKey');
        }

        $serverPubRaw = hex2bin(substr(ANKERSOLIX_SERVER_PUBLIC_KEY, 2));
        $x            = substr($serverPubRaw, 0, 32);
        $y            = substr($serverPubRaw, 32, 32);
        $serverPubKey = openssl_pkey_get_public($this->BuildEcSpki($x, $y));
        $clientKey    = openssl_pkey_get_private($privPem);
        $sharedSecret = openssl_pkey_derive($serverPubKey, $clientKey);

        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $sharedSecret, OPENSSL_RAW_DATA, substr($sharedSecret, 0, 16));
        return base64_encode($encrypted);
    }

    private function BuildEcSpki(string $x, string $y): string
    {
        $header = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der    = $header . "\x04" . $x . $y;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function GetTimezoneOffsetMs(): int
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        return $tz->getOffset(new DateTime('now', $tz)) * 1000;
    }

    // ── HTTP ────────────────────────────────────────────────────────────────────

    private function ApiRequest(string $path, array $payload, ?array $token): array
    {
        $country = $this->ReadPropertyString('Country');
        $tzMs    = $this->GetTimezoneOffsetMs();
        $sign    = ($tzMs >= 0 ? '+' : '-') . sprintf('%02d:%02d', abs((int)($tzMs / 3600000)), abs((int)(($tzMs % 3600000) / 60000)));

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'model-type: DESKTOP',
            'app-name: anker_power',
            'app-version: 1.3.2',
            'os-type: android',
            'timezone: GMT' . $sign,
            'country: ' . $country,
        ];

        if ($token !== null) {
            $headers[] = 'x-auth-token: ' . $token['auth_token'];
            $headers[] = 'gtoken: ' . md5($token['user_id']);
        }

        $ch = curl_init(ANKERSOLIX_API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new Exception('cURL Fehler (' . $errno . '): ' . $error);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new Exception('Ungültige API-Antwort: ' . substr($body, 0, 200));
        }

        return $data;
    }
}
