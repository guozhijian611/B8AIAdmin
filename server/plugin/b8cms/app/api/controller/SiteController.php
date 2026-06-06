<?php

namespace plugin\b8cms\app\api\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\b8cms\app\service\SiteService;
use support\Request;
use support\Response;

#[Apidoc\Group('B8CMS')]
#[Apidoc\Title('独立站公开接口')]
class SiteController
{
    public function __construct(private readonly SiteService $site = new SiteService())
    {
    }

    #[Apidoc\Title('站点启动数据')]
    #[Apidoc\Url('/app/b8cms/api/site/bootstrap')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('lang', type: 'string', require: false, desc: '语言标识，例如 zh-CN/en-US')]
    public function bootstrap(Request $request): Response
    {
        return ok($this->site->bootstrap($request->input('lang')));
    }

    #[Apidoc\Title('内容列表')]
    #[Apidoc\Url('/app/b8cms/api/content/list')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('type', type: 'string', require: true, desc: '内容类型 article/product/page')]
    #[Apidoc\Query('lang', type: 'string', require: false, desc: '语言标识')]
    #[Apidoc\Query('category', type: 'string', require: false, desc: '分类')]
    #[Apidoc\Query('keyword', type: 'string', require: false, desc: '关键词')]
    public function contentList(Request $request): Response
    {
        $type = (string) $request->input('type', 'article');
        if (!in_array($type, ['article', 'product', 'page'], true)) {
            return fail('内容类型不正确');
        }

        return ok($this->site->contentList($type, $request->input('lang'), $request->all()));
    }

    #[Apidoc\Title('内容详情')]
    #[Apidoc\Url('/app/b8cms/api/content/detail')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('type', type: 'string', require: true, desc: '内容类型 article/product/page')]
    #[Apidoc\Query('slug', type: 'string', require: true, desc: '访问别名')]
    #[Apidoc\Query('lang', type: 'string', require: false, desc: '语言标识')]
    public function contentDetail(Request $request): Response
    {
        $type = (string) $request->input('type', 'article');
        $slug = (string) $request->input('slug', '');
        if (!in_array($type, ['article', 'product', 'page'], true) || $slug === '') {
            return fail('参数错误');
        }

        $content = $this->site->contentDetail($type, $slug, $request->input('lang'));
        return $content ? ok($content) : fail('内容不存在', 404);
    }

    #[Apidoc\Title('文章评论列表')]
    #[Apidoc\Url('/app/b8cms/api/comment/list')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('content_id', type: 'int', require: true, desc: '文章ID')]
    public function commentList(Request $request): Response
    {
        $contentId = (int) $request->input('content_id', 0);
        if ($contentId <= 0) {
            return fail('文章ID必须填写');
        }

        return ok($this->site->commentList($contentId));
    }

    #[Apidoc\Title('提交文章评论')]
    #[Apidoc\Url('/app/b8cms/api/comment/submit')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('content_id', type: 'int', require: true, desc: '文章ID')]
    #[Apidoc\Param('parent_id', type: 'int', require: false, desc: '父级评论ID')]
    #[Apidoc\Param('nickname', type: 'string', require: true, desc: '昵称')]
    #[Apidoc\Param('email', type: 'string', require: true, desc: '邮箱')]
    #[Apidoc\Param('comment', type: 'string', require: true, desc: '评论内容')]
    #[Apidoc\Param('website', type: 'string', require: false, desc: '个人网站')]
    #[Apidoc\Param('browser_fingerprint', type: 'string', require: false, desc: '浏览器指纹')]
    #[Apidoc\Param('source_url', type: 'string', require: false, desc: '来源页面')]
    public function submitComment(Request $request): Response
    {
        try {
            $result = $this->site->submitComment($request);
        } catch (\InvalidArgumentException $exception) {
            return fail($exception->getMessage());
        }

        return ok($result, $result['status'] === 3 ? '评论已提交，因命中规则暂不展示' : '评论提交成功');
    }

    #[Apidoc\Title('提交联系表单')]
    #[Apidoc\Url('/app/b8cms/api/contact/submit')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '姓名')]
    #[Apidoc\Param('email', type: 'string', require: true, desc: '邮箱')]
    #[Apidoc\Param('message', type: 'string', require: true, desc: '留言内容')]
    public function submitContact(Request $request): Response
    {
        if ($request->post('name', '') === '' || $request->post('email', '') === '' || $request->post('message', '') === '') {
            return fail('姓名、邮箱和留言内容必须填写');
        }

        $id = $this->site->submitContact($request);
        return ok(['id' => $id], '提交成功');
    }
}
