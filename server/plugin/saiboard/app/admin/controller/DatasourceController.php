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
    #[Permission('数据源列表', 'saiboard:datasource:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('数据源读取')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/read')]
    #[Apidoc\Method('GET')]
    #[Permission('数据源读取', 'saiboard:datasource:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('数据源添加')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/save')]
    #[Apidoc\Method('POST')]
    #[Permission('数据源添加', 'saiboard:datasource:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('数据源修改')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/update')]
    #[Apidoc\Method('PUT')]
    #[Permission('数据源修改', 'saiboard:datasource:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Apidoc\Title('数据源删除')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Permission('数据源删除', 'saiboard:datasource:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Apidoc\Title('数据源状态')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Permission('数据源状态', 'saiboard:datasource:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Apidoc\Title('测试数据源')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/test')]
    #[Apidoc\Method('POST')]
    #[Permission('测试数据源', 'saiboard:datasource:test')]
    public function test(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $datasource = null;
        try {
            $datasource = $id > 0 ? $this->logic->read($id) : $this->makeTestingDatasource($request->post());

            $result = $this->executor->testDatasource($datasource);
            if ($id > 0 && (string) $datasource->last_error !== '') {
                $datasource->save(['last_error' => null]);
            }
            return $this->success($result, '连接成功');
        } catch (Throwable $exception) {
            $message = $this->safeTestError($exception->getMessage());
            if ($id > 0 && $datasource instanceof Datasource && !$datasource->isEmpty()) {
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
        $config = $data['config'] ?? [];
        if (!is_array($config)) {
            throw new InvalidArgumentException('数据源连接配置不正确');
        }

        $this->assertTestingConfig($type, $config);
        $data['config'] = $config;
        return new Datasource($data);
    }

    private function assertTestingConfig(string $type, array $config): void
    {
        if ($type === 'mysql') {
            foreach ([
                'host' => 'MySQL 主机必须填写',
                'database' => 'MySQL 数据库必须填写',
                'username' => 'MySQL 用户名必须填写',
            ] as $key => $message) {
                if (trim((string) ($config[$key] ?? '')) === '') {
                    throw new InvalidArgumentException($message);
                }
            }

            $port = (int) ($config['port'] ?? 3306);
            if ($port < 1 || $port > 65535) {
                throw new InvalidArgumentException('MySQL 端口必须在 1-65535 之间');
            }
            return;
        }

        if ($type === 'http') {
            $url = trim((string) ($config['url'] ?? ''));
            if ($url === '') {
                throw new InvalidArgumentException('HTTP 数据源 URL 必须填写');
            }
            if (!is_array($config['headers'] ?? []) || !is_array($config['params'] ?? [])) {
                throw new InvalidArgumentException('HTTP 请求头和默认参数必须是 JSON 对象');
            }
        }
    }

    private function safeTestError(string $message): string
    {
        $message = preg_replace('/(password|token|secret|authorization|cookie)([^,;\s]*)/i', '$1=***', $message) ?: '连接失败';
        return mb_substr($message, 0, 500);
    }
}
