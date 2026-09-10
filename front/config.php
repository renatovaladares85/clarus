<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Clarus\ClarusConfig;

include dirname(__DIR__, 3) . '/inc/includes.php';

\Session::checkRight('config', UPDATE);
\Plugin::load('clarus');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    /** @var array<string, mixed> $input */
    $input = $_POST;
    \Session::checkCSRF($input);
    try {
        ClarusConfig::update($input);
        \Session::addMessageAfterRedirect(__('Clarus configuration saved.', 'clarus'));
        $pluginWebDir = \Plugin::getWebDir('clarus');
        \Html::redirect((is_string($pluginWebDir) ? $pluginWebDir : '/plugins/clarus') . '/front/config.php');
        exit;
    } catch (\InvalidArgumentException) {
        $error = __('The submitted Clarus configuration is invalid.', 'clarus');
    }
}

$config = ClarusConfig::get();
$pluginWebDir = \Plugin::getWebDir('clarus');
$action = (is_string($pluginWebDir) ? $pluginWebDir : '/plugins/clarus') . '/front/config.php';
$sortLevels = $config[ClarusConfig::INITIAL_SORT];
while (count($sortLevels) < ClarusConfig::MAX_SORT_LEVELS) {
    $sortLevels[] = ['field' => '', 'direction' => 'asc'];
}

\Html::header(__('Clarus configuration', 'clarus'), $action, 'config', 'plugins');
TemplateRenderer::getInstance()->display('@clarus/config.html.twig', [
    'action' => $action,
    'csrfToken' => \Session::getNewCSRFToken(),
    'config' => $config,
    'error' => $error,
    'maxRuleLimit' => ClarusConfig::MAX_RULE_LIMIT,
    'pageSizeOptions' => ClarusConfig::PAGE_SIZE_OPTIONS,
    'groupOptions' => [
        'processing' => __('Processing order', 'clarus'),
        'result' => __('Result', 'clarus'),
        'entity' => __('Entity', 'clarus'),
    ],
    'sortLevels' => $sortLevels,
    'sortOptions' => [
        'result' => __('Result', 'clarus'),
        'adherence' => __('Confirmed adherence', 'clarus'),
        'matches' => __('Matching criteria', 'clarus'),
        'criteria' => __('Configured criteria', 'clarus'),
        'indeterminate' => __('Not evaluated criteria', 'clarus'),
        'ranking' => __('Ranking', 'clarus'),
        'entity' => __('Entity', 'clarus'),
        'condition' => __('Condition', 'clarus'),
        'actions' => __('Configured actions', 'clarus'),
        'name' => __('Rule name', 'clarus'),
        'id' => __('Rule ID', 'clarus'),
    ],
    'booleanFields' => [
        [
            'name' => ClarusConfig::AUTO_INSPECTION,
            'label' => __('Inspect automatically when opening the tab', 'clarus'),
            'checked' => $config[ClarusConfig::AUTO_INSPECTION],
        ],
        [
            'name' => ClarusConfig::INCLUDE_ONADD,
            'label' => __('Select rules on add (ONADD)', 'clarus'),
            'checked' => $config[ClarusConfig::INCLUDE_ONADD],
        ],
        [
            'name' => ClarusConfig::INCLUDE_ONUPDATE,
            'label' => __('Select rules on update (ONUPDATE)', 'clarus'),
            'checked' => $config[ClarusConfig::INCLUDE_ONUPDATE],
        ],
        [
            'name' => ClarusConfig::INCLUDE_ACTIONS,
            'label' => __('Analyze configured actions', 'clarus'),
            'checked' => $config[ClarusConfig::INCLUDE_ACTIONS],
        ],
    ],
    'labels' => [
        'title' => __('Clarus configuration', 'clarus'),
        'description' => __('Configure read-only Ticket rule inspection.', 'clarus'),
        'ruleLimit' => __('Evaluated rule limit', 'clarus'),
        'ruleLimitHint' => sprintf(
            __('Positive integer up to %d. The limit truncates evaluation, not pagination.', 'clarus'),
            ClarusConfig::MAX_RULE_LIMIT
        ),
        'pageSize' => __('Rules per page', 'clarus'),
        'initialGroup' => __('Initial grouping', 'clarus'),
        'minimumAdherence' => __('Default minimum confirmed adherence', 'clarus'),
        'minimumAdherenceHint' => __('Enter a value from 0% to 100%. For example: 80%.', 'clarus'),
        'initialSort' => __('Default sort order', 'clarus'),
        'sortLevel' => __('Level %d', 'clarus'),
        'noSort' => __('No additional sort', 'clarus'),
        'ascending' => __('Ascending', 'clarus'),
        'descending' => __('Descending', 'clarus'),
        'save' => _sx('button', 'Save'),
    ],
]);
\Html::footer();
