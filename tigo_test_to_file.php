<?php

function testAuth($method, $user, $pass, $name) {
    $out = "=====================================\n";
    $out .= "Testing $name with $method...\n";
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

    $out .= "HTTP Status: $httpcode\n";
    $out .= "Response: $result\n\n";
    return $out;
}

$user = "yamankutx";
$pass = 'C00p3$26';
$apiKey = "GUJePiJaqppA3bWjsUMh0BHYILSlk3Qx";
$apiSecret = "S5qtGjwOWJWivZ35qdfq7rEhEFlauWTVlhJqUlRGEHiyRt27C3NwXKkj";

$output = "";
$output .= testAuth('Basic', $user, $pass, 'User/Password');
$output .= testAuth('Basic', $apiKey, $apiSecret, 'APIKey/APISecret');
$output .= testAuth('Body', $user, $pass, 'User/Password');
$output .= testAuth('Body', $apiKey, $apiSecret, 'APIKey/APISecret');

file_put_contents("tigo_results.log", $output);
echo "Done\n";
