<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\service\queue;

use plugin\saiadmin\app\cache\UserInfoCache;
use plugin\saiadmin\app\model\tool\QueueTask;
use plugin\saiadmin\exception\ApiException;
use support\Container;
use support\Context;

/**
 * 队列任务执行器
 */
class QueueExecutorService
{
    public function consume(int $id): bool
    {
        $model = QueueTask::findOrEmpty($id);
        if ($model->isEmpty()) {
            return true;
        }
        if ((int) $model->status > 0) {
            return true;
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $request = json_decode((string) $model->request, true) ?: [];
        $class = $request['class'] ?? $model->class_name;
        $method = $request['method'] ?? $model->method_name;
        $arguments = $request['arguments'] ?? [];

        $model->status = 3;
        if ($class === '' || !class_exists($class)) {
            $model->response = $class . '类不存在';
            $this->saveRuntime($model, $startTime, $startMemory);
            return true;
        }
        if ($method === '' || !method_exists($class, $method)) {
            $model->response = $class . '类中' . $method . '方法不存在';
            $this->saveRuntime($model, $startTime, $startMemory);
            return true;
        }

        $model->status = 1;
        $model->save();

        try {
            if (!empty($model->created_by)) {
                $userInfoCache = new UserInfoCache((int) $model->created_by);
                Context::set('adminInfo', $userInfoCache->getUserInfo());
            }

            $ref = new \ReflectionMethod($class, $method);
            $target = $ref->isStatic() ? $class : Container::make($class, []);
            $model->response = call_user_func([$target, $method], ...$arguments);
            $model->status = 2;
        } catch (ApiException $e) {
            $model->response = ['code' => $e->getCode(), 'msg' => $e->getMessage()];
            $model->status = 2;
        } catch (\Throwable $e) {
            $model->response = [
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'errCode' => $e->getCode(),
                'errFile' => $e->getFile(),
                'errLine' => $e->getLine(),
            ];
            $model->status = 3;
            $model->err_num = (int) $model->err_num + 1;
        } finally {
            $this->saveRuntime($model, $startTime, $startMemory);
            Context::destroy();
        }

        return true;
    }

    private function saveRuntime(QueueTask $model, float $startTime, int $startMemory): void
    {
        $model->response = is_array($model->response)
            ? json_encode($model->response, JSON_UNESCAPED_UNICODE)
            : (string) $model->response;
        $model->run_time = round((microtime(true) - $startTime) * 1000, 2);
        $model->run_memory = round(abs((memory_get_usage() - $startMemory) / 1024 / 1024), 2);
        $model->io = Context::get('ioLogs', '');
        $model->save();
    }
}
