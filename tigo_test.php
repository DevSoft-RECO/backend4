<?php

function testAuth($method, $user, $pass, $name) {
    echo "=====================================\n";
    echo "Testing $name with $method...\n";
    $ch = curl_init('https://prod.api.tigo.com/oauth/client_credential/accesstoken?grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    if ($method == 'Basic') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
        curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    } else if ($method == 'Body') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $user,
            'client_secret' => $pass
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }

    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: $httpcode\n";
    echo "Response: $result\n\n";
}

$user = "yamankutx";
$pass = 'C00p3$26';
$apiKey = "GUJePiJaqppA3bWjsUMh0BHYILSlk3Qx";
$apiSecret = "S5qtGjwOWJWivZ35qdfq7rEhEFlauWTVlhJqUlRGEHiyRt27C3NwXKkj";

ob_start();

testAuth('Basic', $user, $pass, 'User/Password');
testAuth('Basic', $apiKey, $apiSecret, 'APIKey/APISecret');
testAuth('Body', $user, $pass, 'User/Password');
testAuth('Body', $apiKey, $apiSecret, 'APIKey/APISecret');

$out = ob_get_clean();
echo $out;
