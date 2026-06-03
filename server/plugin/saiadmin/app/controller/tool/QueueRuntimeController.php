<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\controller\tool;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\app\service\queue\QueueRuntimeService;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

/**
 * 队列运行状态控制器
 */
#[Apidoc\Group('工具')]
#[Apidoc\Title('队列运行状态')]
class QueueRuntimeController extends BaseController
{
    private QueueRuntimeService $service;

    public function __construct()
    {
        $this->service = new QueueRuntimeService();
        parent::__construct();
    }

    #[Apidoc\Title('实时队列列表')]
    #[Apidoc\Url('/tool/queueRuntime/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('driver', type: 'string', require: false, desc: '队列驱动 redis/rabbitmq')]
    #[Apidoc\Query('message_mode', type: 'string', require: false, desc: '队列用途 internal_job/external_message')]
    #[Apidoc\Query('queue_name', type: 'string', require: false, desc: '队列名称')]
    #[Apidoc\Query('status', type: 'int', require: false, desc: '状态 1启用 2禁用')]
    #[Permission('实时队列列表', 'tool:queue-runtime:index')]
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['name', ''],
            ['driver', ''],
            ['message_mode', ''],
            ['connection', ''],
            ['queue_name', ''],
            ['status', ''],
        ]);
        return $this->success($this->service->runtimeList($where));
    }

    #[Apidoc\Title('清空实时队列')]
    #[Apidoc\Url('/tool/queueRuntime/purge')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '队列配置ID')]
    #[Permission('清空实时队列', 'tool:queue-runtime:purge')]
    public function purge(Request $request): Response
    {
        $result = $this->service->purge((int) $request->post('id'));
        return $this->success($result);
    }
}
