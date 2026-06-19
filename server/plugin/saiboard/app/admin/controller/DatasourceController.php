<?php

namespace plugin\saiboard\app\admin\controller;

use InvalidArgumentException;
use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\service\Permission;
use plugin\saiboard\app\admin\logic\DatasourceLogic;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\service\DataSourceExecutor;
use plugin\saiboard\app\validate\DatasourceValidate;
use support\Request;
use support\Response;
use Throwable;

#[Apidoc\Group('SAI Board')]
#[Apidoc\Title('数据源管理')]
class DatasourceController extends AbstractCrudController
{
    public function __construct(private readonly DataSourceExecutor $executor = new DataSourceExecutor())
    {
        $this->logic = new DatasourceLogic();
        $this->validate = new DatasourceValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['name', ''],
            ['type', ''],
            ['status', ''],
        ];
    }

    #[Apidoc\Title('数据源列表')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Returned('total', type: 'int', desc: '总记录数')]
    #[Permission('数据源列表', 'saiboard:datasource:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('数据源读取')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '数据源ID')]
    #[Apidoc\Returned('id', type: 'int', desc: '数据源ID')]
    #[Apidoc\Returned('name', type: 'string', desc: '数据源名称')]
    #[Apidoc\Returned('type', type: 'string', desc: '数据源类型：mysql/http')]
    #[Apidoc\Returned('config', type: 'object', desc: '数据源配置，敏感字段按读取策略脱敏')]
    #[Permission('数据源读取', 'saiboard:datasource:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('数据源添加')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/save')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '数据源名称')]
    #[Apidoc\Param('type', type: 'string', require: true, desc: '数据源类型：mysql/http')]
    #[Apidoc\Param('config', type: 'object', require: true, desc: '连接配置')]
    #[Permission('数据源添加', 'saiboard:datasource:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('数据源修改')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/update')]
    #[Apidoc\Method('PUT')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '数据源ID')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '数据源名称')]
    #[Apidoc\Param('type', type: 'string', require: true, desc: '数据源类型：mysql/http')]
    #[Apidoc\Param('config', type: 'object', require: true, desc: '连接配置')]
    #[Permission('数据源修改', 'saiboard:datasource:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Apidoc\Title('数据源删除')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Apidoc\Param('ids', type: 'array', require: true, desc: '数据源ID列表')]
    #[Permission('数据源删除', 'saiboard:datasource:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Apidoc\Title('数据源状态')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '数据源ID')]
    #[Apidoc\Param('status', type: 'int', require: true, desc: '状态 1启用 2停用')]
    #[Permission('数据源状态', 'saiboard:datasource:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Apidoc\Title('测试数据源')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/test')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: false, desc: '已保存数据源ID；为空或同时传 config 时按当前表单配置测试')]
    #[Apidoc\Param('name', type: 'string', require: false, desc: '新增态测试数据源名称')]
    #[Apidoc\Param('type', type: 'string', require: false, desc: '新增态测试数据源类型：mysql/http')]
    #[Apidoc\Param('status', type: 'int', require: false, desc: '新增态测试状态 1启用 2停用')]
    #[Apidoc\Param('config', type: 'object', require: false, desc: '新增态测试连接配置')]
    #[Apidoc\Param('test_config', type: 'object', require: false, desc: 'HTTP 测试模板配置：path/method/params/body/response_path/total_path')]
    #[Apidoc\Returned('rows', type: 'array', desc: '测试返回数据行')]
    #[Apidoc\Returned('total', type: 'int', desc: '测试返回总数')]
    #[Apidoc\Returned('diagnostics', type: 'object', desc: '脱敏诊断信息')]
    #[Permission('测试数据源', 'saiboard:datasource:test')]
    public function test(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $datasource = null;
        $data = $request->post();
        $isDraftTest = $id <= 0 || array_key_exists('config', $data);
        $persistLastError = !$isDraftTest && $id > 0;
        try {
            $datasource = $isDraftTest ? $this->makeTestingDatasource($data) : $this->logic->read($id);

            $result = $this->executor->testDatasource(
                $datasource,
                $isDraftTest ? $this->testingTemplateConfig($request->post('test_config', [])) : []
            );
            if ($persistLastError && (string) $datasource->last_error !== '') {
                $datasource->save(['last_error' => null]);
            }
            return $this->success($result, '连接成功');
        } catch (Throwable $exception) {
            $message = $this->safeTestError($exception->getMessage());
            if ($persistLastError && $datasource instanceof Datasource && !$datasource->isEmpty()) {
                $datasource->save(['last_error' => $message]);
            }
            return $this->fail($message);
        }
    }

    #[Apidoc\Title('数据源选项')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/options')]
    #[Apidoc\Method('GET')]
    #[Permission('数据源选项', 'saiboard:datasource:index')]
    public function options(Request $request): Response
    {
        return $this->success($this->logic->enabledOptions());
    }

    #[Apidoc\Title('数据源表结构')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/schema')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '数据源ID')]
    #[Apidoc\Query('table', type: 'string', require: false, desc: '数据表名')]
    #[Apidoc\Returned('tables', type: 'array', desc: '数据表列表')]
    #[Apidoc\Returned('columns', type: 'array', desc: '指定数据表字段列表')]
    #[Permission('数据源表结构', 'saiboard:datasource:index')]
    public function schema(Request $request): Response
    {
        return $this->success(
            $this->executor->schema(
                $this->logic->enabled((int) $request->input('id', 0)),
                (string) $request->input('table', '')
            )
        );
    }

    private function makeTestingDatasource(array $data): Datasource
    {
        $this->validate('test', $data);
        $type = (string) ($data['type'] ?? '');
        $data['config'] = $this->executor->normalizeDatasourceConfig($type, $data['config'] ?? []);
        return new Datasource($data);
    }

    private function safeTestError(string $message): string
    {
        $message = $this->friendlyTestError($message);
        $message = preg_replace('/(password|token|secret|authorization|cookie)([^,;\s]*)/i', '$1=***', $message) ?: '连接失败';
        return mb_substr($message, 0, 500);
    }

    private function isObjectConfig(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    private function testingTemplateConfig(mixed $config): array
    {
        if ($config === null || $config === '') {
            return [];
        }
        if (!$this->isObjectConfig($config)) {
            throw new InvalidArgumentException('HTTP 测试配置必须是 JSON 对象');
        }

        return $config;
    }

    private function friendlyTestError(string $message): string
    {
        if (preg_match("/Unknown database '([^']+)'/i", $message, $matches)) {
            return 'MySQL 数据库不存在：' . $matches[1];
        }
        if (str_contains($message, '[1045]')) {
            return 'MySQL 用户名或密码不正确';
        }
        if (str_contains($message, '[2002]')) {
            return 'MySQL 主机或端口无法连接';
        }
        if (str_contains($message, '[2003]')) {
            return 'MySQL 连接被拒绝，请检查主机和端口';
        }

        return $message;
    }
}
