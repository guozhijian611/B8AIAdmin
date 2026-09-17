<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\saiai\app\admin\logic\chat;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\utils\Helper;
use plugin\saiai\app\model\chat\AiChat;

/**
 * 对话记录逻辑层
 */
class AiChatLogic extends BaseLogic
{
    /**
     * 域策略：业务数据按 created_by 做数据范围隔离（非 tenant 多租户）
     */
    protected bool $scope = true;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->model = new AiChat();
    }

}
