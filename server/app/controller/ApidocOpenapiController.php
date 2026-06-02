<?php

namespace app\controller;

use hg\apidoc\export\ExportSwagger;
use hg\apidoc\middleware\WebmanMiddleware;
use hg\apidoc\utils\ApiShare;
use hg\apidoc\utils\ConfigProvider;
use hg\apidoc\utils\Helper;
use support\Request;
use support\Response;

class ApidocOpenapiController
{
    public function show(Request $request, string $appKey): Response
    {
        $config = WebmanMiddleware::getApidocConfig();
        ConfigProvider::set($config);

        $apps = Helper::handleAppsConfig($config['apps'], false, $config);
        $apiData = ApiShare::getAppShareApis($config, $apps, '', [$appKey], true);

        if (empty($apiData)) {
            return json([
                'code' => 404,
                'message' => "APIDOC app key [{$appKey}] 不存在",
            ], 404);
        }

        $openapi = (new ExportSwagger($config['export_config']))->exportJson($config, [
            'shareData' => [
                'type' => 'app',
                'appKeys' => [$appKey],
            ],
            'apiData' => $apiData,
        ]);

        return json($openapi);
    }
}
