<?php
// +----------------------------------------------------------------------
// | saiuser plugin
// +----------------------------------------------------------------------
namespace plugin\saiuser\app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use Tinywan\Jwt\JwtToken;
use plugin\saiadmin\app\cache\ReflectionCache;
use plugin\saiadmin\exception\ApiException;

/**
 * 会员登录检查中间件
 */
class CheckMemberLogin implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 通过反射获取控制器哪些方法不需要登录
        $noNeedLogin = ReflectionCache::getNoNeedLogin($request->controller);
        // 访问的方法需要登录
        if (!in_array($request->action, $noNeedLogin)) {
            try {
                $token = JwtToken::getExtend();
            } catch (\Throwable $e) {
                throw new ApiException('您的登录凭证错误或者已过期，请重新登录', 401);
            }
            if (!isset($token['plat']) || $token['plat'] !== 'saiuser') {
                throw new ApiException('登录凭证校验失败', 401);
            }
            $request->setHeader('check_member_login', true);
            $request->setHeader('check_member', $token);
        }
        return $handler($request);
    }
}
