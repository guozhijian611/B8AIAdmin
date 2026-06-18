<?php

namespace plugin\saiboard\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class QueryTemplateValidate extends BaseValidate
{
    protected $rule = [
        'datasource_id' => 'require|integer',
        'name' => 'require|max:60',
        'dataset_type' => 'require|in:table_raw,table_count,http_passthrough',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'datasource_id.require' => '数据源必须选择',
        'datasource_id.integer' => '数据源不正确',
        'name.require' => '模板名称必须填写',
        'name.max' => '模板名称最多60个字符',
        'dataset_type.require' => '取数类型必须选择',
        'dataset_type.in' => '取数类型不正确',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['datasource_id', 'name', 'dataset_type', 'status'],
        'update' => ['datasource_id', 'name', 'dataset_type', 'status'],
    ];
}
