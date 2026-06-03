<?php

declare(strict_types=1);

namespace plugin\saiadmin\app\controller\system;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\app\service\LogReaderTicketService;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

/**
 * 日志查看器控制器
 */
#[Apidoc\Group('运维管理')]
#[Apidoc\Title('日志查看器')]
class LogReaderController extends BaseController
{
    /**
     * 签发日志查看器访问票据
     */
    #[Apidoc\Title('签发日志查看器访问票据')]
    #[Apidoc\Url('/core/log-reader/ticket')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Returned('url', type: 'string', desc: '日志查看器入口')]
    #[Apidoc\Returned('expires_in', type: 'int', desc: '票据有效秒数')]
    #[Permission('日志查看器', 'core:log-reader:ticket')]
    public function ticket(Request $request): Response
    {
        if (!LogReaderTicketService::enabled()) {
            return $this->fail('日志查看器未启用');
        }

        return $this->success(LogReaderTicketService::issue($request, $this->adminId));
    }
}
