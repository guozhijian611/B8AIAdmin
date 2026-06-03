<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\controller\tool;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\app\logic\tool\QueueTaskLogic;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

/**
 * 队列任务控制器
 */
#[Apidoc\Group('工具')]
#[Apidoc\Title('队列任务')]
class QueueTaskController extends BaseController
{
    public function __construct()
    {
        $this->logic = new QueueTaskLogic();
        parent::__construct();
    }

    #[Apidoc\Title('队列任务列表')]
    #[Apidoc\Url('/tool/queueTask/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('config_id', type: 'int', require: false, desc: '队列配置ID')]
    #[Apidoc\Query('driver', type: 'string', require: false, desc: '队列驱动')]
    #[Apidoc\Query('status', type: 'int', require: false, desc: '任务状态')]
    #[Permission('队列任务列表', 'tool:queue-task:index')]
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['config_id', ''],
            ['driver', ''],
            ['connections', ''],
            ['name', ''],
            ['status', ''],
            ['class_name', ''],
            ['method_name', ''],
            ['source', ''],
            ['create_time', []],
        ]);
        $data = $this->logic->getList($this->logic->search($where));
        return $this->success($data);
    }

    #[Apidoc\Title('读取队列任务')]
    #[Apidoc\Url('/tool/queueTask/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '任务ID')]
    #[Permission('读取队列任务', 'tool:queue-task:read')]
    public function read(Request $request): Response
    {
        $data = $this->logic->read((int) $request->input('id'));
        return $this->success($data->toArray());
    }

    #[Apidoc\Title('删除队列任务')]
    #[Apidoc\Url('/tool/queueTask/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Permission('队列任务删除', 'tool:queue-task:destroy')]
    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids', []);
        if (empty($ids)) {
            return $this->fail('请选择要删除的数据');
        }
        $this->logic->destroy($ids);
        return $this->success('删除成功');
    }

    #[Apidoc\Title('重试队列任务')]
    #[Apidoc\Url('/tool/queueTask/retry')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '任务ID')]
    #[Permission('队列任务重试', 'tool:queue-task:retry')]
    public function retry(Request $request): Response
    {
        $this->logic->retry((int) $request->post('id'));
        return $this->success('重试投递成功');
    }

    #[Apidoc\Title('取消队列任务')]
    #[Apidoc\Url('/tool/queueTask/cancel')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '任务ID')]
    #[Permission('队列任务取消', 'tool:queue-task:cancel')]
    public function cancel(Request $request): Response
    {
        $this->logic->cancel((int) $request->post('id'));
        return $this->success('取消成功');
    }

    #[Apidoc\Title('清理已完成队列任务')]
    #[Apidoc\Url('/tool/queueTask/clearCompleted')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('config_id', type: 'int', require: false, desc: '队列配置ID')]
    #[Permission('队列任务清理', 'tool:queue-task:clear')]
    public function clearCompleted(Request $request): Response
    {
        $count = $this->logic->clearCompleted($request->post('config_id') ? (int) $request->post('config_id') : null);
        return $this->success(['count' => $count], '清理成功');
    }

    #[Apidoc\Title('队列任务统计')]
    #[Apidoc\Url('/tool/queueTask/stats')]
    #[Apidoc\Method('GET')]
    #[Permission('队列任务列表', 'tool:queue-task:index')]
    public function stats(): Response
    {
        return $this->success($this->logic->stats());
    }
}
