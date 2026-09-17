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
use plugin\saiuser\app\model\cms\Article;

/**
 * 文章列表逻辑层
 */
class ArticleLogic extends BaseLogic
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
        $this->model = new Article();
    }

    /**
     * 读取数据
     * @param $id
     * @return array
     */
    public function read($id): array
    {
        $admin = $this->model->find($id);
        $data = $admin->toArray();
        $data['category'] = $admin->category->toArray() ?: [];
        return $data;
    }

}
