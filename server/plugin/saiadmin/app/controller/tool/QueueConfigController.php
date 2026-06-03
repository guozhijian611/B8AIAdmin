<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\controller\tool;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\app\logic\tool\QueueConfigLogic;
use plugin\saiadmin\app\validate\tool\QueueConfigValidate;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

/**
 * 队列配置控制器
 */
#[Apidoc\Group('工具')]
#[Apidoc\Title('队列配置')]
class QueueConfigController extends BaseController
{
    public function __construct()
    {
        $this->logic = new QueueConfigLogic();
        $this->validate = new QueueConfigValidate();
        parent::__construct();
    }

    #[Apidoc\Title('队列配置列表')]
    #[Apidoc\Url('/tool/queueConfig/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('driver', type: 'string', require: false, desc: '队列驱动 redis/rabbitmq')]
    #[Apidoc\Query('queue_name', type: 'string', require: false, desc: '队列名称')]
    #[Apidoc\Query('status', type: 'int', require: false, desc: '状态 1启用 2禁用')]
    #[Permission('队列配置列表', 'tool:queue-config:index')]
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['name', ''],
            ['driver', ''],
            ['connection', ''],
            ['queue_name', ''],
            ['status', ''],
        ]);
        $data = $this->logic->getList($this->logic->search($where));
        return $this->success($data);
    }

    #[Apidoc\Title('读取队列配置')]
    #[Apidoc\Url('/tool/queueConfig/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '配置ID')]
    #[Permission('读取队列配置', 'tool:queue-config:read')]
    public function read(Request $request): Response
    {
        $data = $this->logic->read((int) $request->input('id'));
        return $this->success($data->toArray());
    }

    #[Apidoc\Title('新增队列配置')]
    #[Apidoc\Url('/tool/queueConfig/save')]
    #[Apidoc\Method('POST')]
    #[Permission('队列配置管理', 'tool:queue-config:edit')]
    public function save(Request $request): Response
    {
        $data = $request->post();
        $this->validate('save', $data);
        $this->logic->add($data);
        return $this->success('添加成功');
    }

    #[Apidoc\Title('更新队列配置')]
    #[Apidoc\Url('/tool/queueConfig/update')]
    #[Apidoc\Method('PUT')]
    #[Permission('队列配置管理', 'tool:queue-config:edit')]
    public function update(Request $request): Response
    {
        $data = $request->post();
        $this->validate('update', $data);
        $this->logic->edit($data['id'], $data);
        return $this->success('修改成功');
    }

    #[Apidoc\Title('删除队列配置')]
    #[Apidoc\Url('/tool/queueConfig/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Permission('队列配置管理', 'tool:queue-config:edit')]
    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids', []);
        if (empty($ids)) {
            return $this->fail('请选择要删除的数据');
        }
        $this->logic->destroy($ids);
        return $this->success('删除成功');
    }

    #[Apidoc\Title('修改队列配置状态')]
    #[Apidoc\Url('/tool/queueConfig/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '配置ID')]
    #[Apidoc\Param('status', type: 'int', require: true, desc: '状态 1启用 2禁用')]
    #[Permission('队列配置状态修改', 'tool:queue-config:status')]
    public function changeStatus(Request $request): Response
    {
        $this->logic->changeStatus((int) $request->post('id'), (int) $request->post('status', 2));
        return $this->success('操作成功，重载 Webman 后消费者进程生效');
    }

    #[Apidoc\Title('启用队列配置选项')]
    #[Apidoc\Url('/tool/queueConfig/options')]
    #[Apidoc\Method('GET')]
    #[Permission('队列配置列表', 'tool:queue-config:index')]
    public function options(): Response
    {
        return $this->success($this->logic->options());
    }
}
