<?php
namespace plugin\saiuser\app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 会员权限检查中间件 (脚手架可用)
 */
class MemberAuth extends CheckMemberLogin
{
    public function process(Request $request, callable $handler): Response
    {
        return parent::process($request, $handler);
    }
}
