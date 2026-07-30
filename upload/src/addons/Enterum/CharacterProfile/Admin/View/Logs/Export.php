<?php

/**
 * ACP: сырой ответ CSV при выгрузке журнала действий.
 */

namespace Enterum\CharacterProfile\Admin\View\Logs;

use XF\Mvc\View;

class Export extends View
{
    /**
     * @return string
     */
    public function renderRaw()
    {
        $fileName = (string)($this->params['fileName'] ?? 'char_profile_logs.csv');
        $csv = (string)($this->params['csv'] ?? '');

        $this->response->contentType('text/csv', 'utf-8');
        $this->response->setDownloadFileName($fileName);

        return $csv;
    }
}
