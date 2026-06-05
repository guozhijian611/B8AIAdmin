<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\saiai\app\admin\controller\config;

use plugin\saiadmin\basic\BaseController;
use plugin\saiai\app\admin\logic\config\AiConfigLogic;
use plugin\saiai\app\admin\validate\config\AiConfigValidate;
use plugin\saiadmin\service\Permission;
use plugin\saiai\app\service\AiFactory;
use plugin\saiai\app\service\AliyunRealtimeConfig;
use support\Request;
use support\Response;

/**
 * AI配置控制器
 */
class AiConfigController extends BaseController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logic = new AiConfigLogic();
        $this->validate = new AiConfigValidate;
        parent::__construct();
    }

    /**
     * 数据列表
     * @param Request $request
     * @return Response
     */
    #[Permission('AI配置列表', 'saiai:config:config:index')]
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['name', ''],
            ['type', ''],
        ]);
        $query = $this->logic->search($where);
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

    /**
     * 读取数据
     * @param Request $request
     * @return Response
     */
    #[Permission('AI配置读取', 'saiai:config:config:read')]
    public function read(Request $request): Response
    {
        $id = $request->input('id', '');
        $model = $this->logic->read($id);
        if ($model) {
            $data = is_array($model) ? $model : $model->toArray();
            return $this->success($data);
        } else {
            return $this->fail('未查找到信息');
        }
    }

    /**
     * 保存数据
     * @param Request $request
     * @return Response
     */
    #[Permission('AI配置添加', 'saiai:config:config:save')]
    public function save(Request $request): Response
    {
        $data = $request->post();
        $this->validate('save', $data);
        $result = $this->logic->add($data);
        if ($result) {
            return $this->success('添加成功');
        } else {
            return $this->fail('添加失败');
        }
    }

    /**
     * 更新数据
     * @param Request $request
     * @return Response
     */
    #[Permission('AI配置修改', 'saiai:config:config:update')]
    public function update(Request $request): Response
    {
        $data = $request->post();
        $this->validate('update', $data);
        $result = $this->logic->edit($data['id'], $data);
        if ($result) {
            return $this->success('修改成功');
        } else {
            return $this->fail('修改失败');
        }
    }

    /**
     * 删除数据
     * @param Request $request
     * @return Response
     */
    #[Permission('AI配置删除', 'saiai:config:config:destroy')]
    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids', '');
        if (empty($ids)) {
            return $this->fail('请选择要删除的数据');
        }
        $result = $this->logic->destroy($ids);
        if ($result) {
            return $this->success('删除成功');
        } else {
            return $this->fail('删除失败');
        }
    }

    /**
     * 模型平台列表
     * @param Request $request
     * @return Response
     */
    public function getAiList(Request $request): Response
    {
        $list = config('plugin.saiai.ai');
        $data = [];
        foreach ($list as $item) {
            $data[] = [
                'label' => $item,
                'value' => $item
            ];
        }
        return $this->success($data);
    }

    /**
     * 获取模型列表
     * @param Request $request
     * @return Response
     */
    public function getModel(Request $request): Response
    {
        $platform = $request->get('platform', 'gemini');
        $modelList = AiFactory::getModelCategory($platform);
        return $this->success($modelList);
    }

    /**
     * 阿里云实时测试页默认配置
     * @param Request $request
     * @return Response
     */
    #[Permission('实时测试配置', 'saiai:realtime:test')]
    public function realtimeTestConfig(Request $request): Response
    {
        $port = (int) env('SAIAI_REALTIME_WS_PORT', 8791);
        $forwardedProto = strtolower((string) $request->header('x-forwarded-proto', ''));
        $scheme = $forwardedProto === 'https' || $request->header('https') === 'on' ? 'wss' : 'ws';
        $host = $request->host(false) ?: '127.0.0.1';
        $host = preg_match('/:\d+$/', $host)
            ? (preg_replace('/:\d+$/', ':' . $port, $host) ?: $host)
            : $host . ':' . $port;

        return $this->success([
            'ws_url' => $scheme . '://' . $host,
            'default_model' => AliyunRealtimeConfig::DEFAULT_MODEL,
            'default_ai_url' => AliyunRealtimeConfig::DEFAULT_URL,
            'default_session' => AliyunRealtimeConfig::defaultSession(),
            'port' => $port,
        ]);
    }

}
