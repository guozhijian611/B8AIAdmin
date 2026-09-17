<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\saiuser\app\admin\logic\cms;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\utils\Helper;
use plugin\saiuser\app\model\cms\ArticleBanner;

/**
 * 轮播列表逻辑层
 */
class ArticleBannerLogic extends BaseLogic
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
        $this->model = new ArticleBanner();
    }

}
