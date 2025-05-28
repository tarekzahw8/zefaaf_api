<?php

declare(strict_types=1);
//---
require __DIR__ . '/src/App/App.php';

$publicKey = <<<EOD
    -----BEGIN PUBLIC KEY-----
    MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCZsw83Sg+Y+1cuMJlz9qQyB8WK
    y+SxFVFTWic9a5jXLpAPYfvfDK+UiaveosXvFp/UepmGCTP44H4y/qEI/7EdBFfZ
    KO8e39CqlbHalthBTZDniSpCrSkjXvG1vmeE20OnuSTOkS+ZGzU5ub2zZPteg1Zn
    LYNQw9gp6+faklJY5wIDAQAB
    -----END PUBLIC KEY-----
    EOD;

// "Secnkj9ne8nkmndsa8enk3usnfw0nlsAS"
$app->add(new Tuupola\Middleware\JwtAuthentication([
    "secret" => $publicKey,
    "secure" => true,
    "algorithm" => ["HS256", "RS256"],
    "before" =>
    function ($request, $arguments) {
        $token = $request->getAttribute("token");
        // echo "token:$token";
        $userId = $token['userId'] ?? null;
        $userGender = $token['userGender'] ?? null;
        $userDeviceToken = $token['userDeviceToken'] ?? null;
        $userName = $token['userName'] ?? null;
        $type = $token['type'] ?? null;


        $request = $request->withAttribute("userId", $userId);
        $request = $request->withAttribute("userGender", $userGender);
        $request = $request->withAttribute("userDeviceToken", $userDeviceToken);
        $request = $request->withAttribute("userName", $userName);
        $request = $request->withAttribute("type", $type);

        return $request;
    },
    "path" => ["/v1", "/v2"],
    // "header" => "Token",
    "ignore" => [

        "/v1/mobile/checkForSpamChat",
        "/v1/mobile/login",
        "/v1/mobile/register",
        "/v1/mobile/checkToken",
        "/v1/mobile/checkMobile",
        "/v1/mobile/checkUserName",
        "/v1/mobile/getPackages",
        "/v1/mobile/getPostsCategories",
        "/v1/mobile/getPosts",
        "/v1/mobile/getSuccessStories",
        "/v1/mobile/getCountries",
        "/v1/mobile/getCities",
        "/v1/mobile/getAppSettings",
        "/v1/mobile/getPostDetails",
        "/v1/mobile/getStoryDetails",
        "/v1/mobile/getWebHome",
        "/v1/mobile/requestChangePassword",
        "/v1/mobile/changePassword",
        "/v1/mobile/testSendEmail",
        "/v1/mobile/vapPay",
        "/v1/mobile/confirmVapPayment",
        "/v1/mobile/confirmPaypalPayment",
        "/v1/mobile/changePasswordNew",
        "/v1/mobile/requestWebPay",

        "/v1/crud/login",
        "/v1/crud/agentLogin",
        "/v1/crud/telesalesLogin",


        // "/api/v1/mobile/requestPay",
        "/v1/mobile/confirmPayment",

    ],
    "error" => function ($response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
]));

$app->run();
