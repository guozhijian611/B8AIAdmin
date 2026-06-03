<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\controller\tool;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\app\logic\tool\QueueMessageLogic;
use plugin\saiadmin\app\validate\tool\QueueMessageValidate;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

/**
 * 队列外部消息控制器
 */
#[Apidoc\Group('工具')]
#[Apidoc\Title('队列消息')]
class QueueMessageController extends BaseController
{
    public function __construct()
    {
        $this->logic = new QueueMessageLogic();
        $this->validate = new QueueMessageValidate();
        parent::__construct();
    }

    #[Apidoc\Title('队列消息列表')]
    #[Apidoc\Url('/tool/queueMessage/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('config_id', type: 'int', require: false, desc: '队列配置ID')]
    #[Apidoc\Query('driver', type: 'string', require: false, desc: '队列驱动')]
    #[Apidoc\Query('status', type: 'int', require: false, desc: '消息状态')]
    #[Permission('队列消息列表', 'tool:queue-message:index')]
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['config_id', ''],
            ['driver', ''],
            ['connections', ''],
            ['name', ''],
            ['status', ''],
            ['event_name', ''],
            ['message_key', ''],
            ['source', ''],
            ['create_time', []],
        ]);
        $data = $this->logic->getList($this->logic->search($where));
        return $this->success($data);
    }

    #[Apidoc\Title('读取队列消息')]
    #[Apidoc\Url('/tool/queueMessage/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '消息ID')]
    #[Permission('读取队列消息', 'tool:queue-message:read')]
    public function read(Request $request): Response
    {
        $data = $this->logic->read((int) $request->input('id'));
        return $this->success($data->toArray());
    }

    #[Apidoc\Title('发布队列消息')]
    #[Apidoc\Url('/tool/queueMessage/publish')]
    #[Apidoc\Method('POST')]
    #[Permission('发布队列消息', 'tool:queue-message:publish')]
    public function publish(Request $request): Response
    {
        $data = $request->post();
        $this->validate('publish', $data);
        $id = $this->logic->publish($data);
        return $this->success(['id' => $id], '发布成功');
    }

    #[Apidoc\Title('删除队列消息')]
    #[Apidoc\Url('/tool/queueMessage/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Permission('队列消息删除', 'tool:queue-message:destroy')]
    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids', []);
        if (empty($ids)) {
            return $this->fail('请选择要删除的数据');
        }
        $this->logic->destroy($ids);
        return $this->success('删除成功');
    }

    #[Apidoc\Title('重试队列消息')]
    #[Apidoc\Url('/tool/queueMessage/retry')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '消息ID')]
    #[Permission('队列消息重试', 'tool:queue-message:retry')]
    public function retry(Request $request): Response
    {
        $this->logic->retry((int) $request->post('id'));
        return $this->success('重试发布成功');
    }

    #[Apidoc\Title('取消队列消息')]
    #[Apidoc\Url('/tool/queueMessage/cancel')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '消息ID')]
    #[Permission('队列消息取消', 'tool:queue-message:cancel')]
    public function cancel(Request $request): Response
    {
        $this->logic->cancel((int) $request->post('id'));
        return $this->success('取消成功');
    }

    #[Apidoc\Title('清理已发布队列消息')]
    #[Apidoc\Url('/tool/queueMessage/clearPublished')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('config_id', type: 'int', require: false, desc: '队列配置ID')]
    #[Permission('队列消息清理', 'tool:queue-message:clear')]
    public function clearPublished(Request $request): Response
    {
        $count = $this->logic->clearPublished($request->post('config_id') ? (int) $request->post('config_id') : null);
        return $this->success(['count' => $count], '清理成功');
    }

    #[Apidoc\Title('队列消息统计')]
    #[Apidoc\Url('/tool/queueMessage/stats')]
    #[Apidoc\Method('GET')]
    #[Permission('队列消息列表', 'tool:queue-message:index')]
    public function stats(): Response
    {
        return $this->success($this->logic->stats());
    }
}
