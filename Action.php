<?php

namespace TypechoPlugin\Copyright;

use Typecho\Widget;
use Widget\ActionInterface;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Copyright action endpoint.
 *
 * 为后续样式预览、CC 协议模板等异步能力预留统一入口。
 */
class Action extends Widget implements ActionInterface
{
    /**
     * 执行动作请求。
     */
    public function action(): void
    {
        if ((string) $this->request->get('schema') === '1') {
            $this->emitJson(200, [
                'ok' => true,
                'data' => Plugin::editorSchema(),
            ]);
            return;
        }

        $this->emitJson(404, [
            'ok' => false,
            'message' => 'Unsupported copyright action request.',
        ]);
    }

    /**
     * 输出 JSON 响应。
     */
    private function emitJson(int $status, array $payload): void
    {
        $this->response->setStatus($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

if (!class_exists('Copyright_Action', false)) {
    class_alias(__NAMESPACE__ . '\\Action', 'Copyright_Action');
}
