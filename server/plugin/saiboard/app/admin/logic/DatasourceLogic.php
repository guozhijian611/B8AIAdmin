<?php

namespace plugin\saiboard\app\admin\logic;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiboard\app\model\Datasource;

class DatasourceLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new Datasource();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function enabledOptions(): array
    {
        return Datasource::where('status', 1)
            ->field('id,name,type')
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }
}
