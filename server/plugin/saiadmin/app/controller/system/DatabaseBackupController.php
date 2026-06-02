<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\controller\system;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\app\logic\system\DatabaseBackupLogic;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

/**
 * 数据库导入导出控制器
 */
#[Apidoc\Group('运维管理')]
#[Apidoc\Title('数据库导入导出')]
class DatabaseBackupController extends BaseController
{
    /**
     * 构造
     */
    public function __construct()
    {
        $this->logic = new DatabaseBackupLogic();
        parent::__construct();
    }

    /**
     * 数据库导入导出概览
     */
    #[Apidoc\Title('数据库导入导出概览')]
    #[Apidoc\Url('/core/database-backup/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('source', type: 'string', require: false, default: 'mysql', desc: '数据库连接名')]
    #[Apidoc\Returned('database', type: 'string', desc: '数据库名')]
    #[Apidoc\Returned('table_count', type: 'int', desc: '数据表数量')]
    #[Permission('数据库导入导出列表', 'core:database-backup:index')]
    public function index(Request $request): Response
    {
        $source = $request->input('source', 'mysql');
        return $this->success($this->logic->overview($source));
    }

    /**
     * 导出 SQL 文件
     */
    #[Apidoc\Title('导出 SQL 文件')]
    #[Apidoc\Url('/core/database-backup/export')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('source', type: 'string', require: false, default: 'mysql', desc: '数据库连接名')]
    #[Apidoc\Param('with_data', type: 'boolean', require: false, default: true, desc: '是否导出表数据')]
    #[Apidoc\Param('tables', type: 'array', require: false, desc: '指定导出的表名列表')]
    #[Apidoc\Param('disable_foreign_key_checks', type: 'boolean', require: false, default: true, desc: '是否禁用外键检查')]
    #[Permission('数据库导出', 'core:database-backup:export')]
    public function export(Request $request): Response
    {
        $data = $request->post();
        $result = $this->logic->exportSql($data);
        return response()->download($result['path'], urlencode($result['filename']));
    }

    /**
     * 导入 SQL 文件
     */
    #[Apidoc\Title('导入 SQL 文件')]
    #[Apidoc\Url('/core/database-backup/import')]
    #[Apidoc\Method('POST')]
    #[Apidoc\ContentType('multipart/form-data')]
    #[Apidoc\Param('file', type: 'file', require: true, desc: 'SQL 文件')]
    #[Apidoc\Param('source', type: 'string', require: false, default: 'mysql', desc: '数据库连接名')]
    #[Apidoc\Param('drop_table_if_exists', type: 'boolean', require: false, default: false, desc: '表已存在时是否先删除')]
    #[Permission('数据库导入', 'core:database-backup:import')]
    public function import(Request $request): Response
    {
        $file = current($request->file());
        if (!$file || !$file->isValid()) {
            return $this->fail('未找到上传文件');
        }

        $this->logic->importSql($file, $request->post());
        return $this->success('导入成功');
    }

    /**
     * 下载本地备份文件
     */
    #[Apidoc\Title('下载本地备份文件')]
    #[Apidoc\Url('/core/database-backup/download')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('filename', type: 'string', require: true, desc: '备份文件名')]
    #[Permission('数据库备份下载', 'core:database-backup:export')]
    public function download(Request $request): Response
    {
        $filename = $request->post('filename', '');
        $path = $this->logic->getBackupPath($filename);
        return response()->download($path, urlencode($filename));
    }

    /**
     * 删除本地备份文件
     */
    #[Apidoc\Title('删除本地备份文件')]
    #[Apidoc\Url('/core/database-backup/delete')]
    #[Apidoc\Method('DELETE')]
    #[Apidoc\Param('filename', type: 'string', require: true, desc: '备份文件名')]
    #[Permission('数据库备份删除', 'core:database-backup:delete')]
    public function delete(Request $request): Response
    {
        $filename = $request->post('filename', '');
        if (empty($filename)) {
            return $this->fail('请选择要删除的备份文件');
        }

        $this->logic->deleteBackup($filename);
        return $this->success('删除成功');
    }
}
