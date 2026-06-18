<?php

namespace plugin\saiboard\app\admin\logic;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiboard\app\model\QueryTemplate;

class QueryTemplateLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new QueryTemplate();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function enabledOptions(int $datasourceId = 0): array
    {
        $query = QueryTemplate::where('status', 1)->field('id,datasource_id,name,dataset_type');
        if ($datasourceId > 0) {
            $query->where('datasource_id', $datasourceId);
        }

        return $query->order('id', 'desc')->select()->toArray();
    }
}
