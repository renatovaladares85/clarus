<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

use GlpiPlugin\Clarus\Authorization;
use GlpiPlugin\Clarus\Profile;
use GlpiPlugin\Clarus\TicketTab;

include dirname(__DIR__, 3) . '/inc/includes.php';

header('Content-Type: text/html; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo htmlspecialchars(__('Method not allowed.', 'clarus'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

/** @var array<string, mixed> $input */
$input = $_POST;

if (!\Session::haveRight(Profile::RIGHT_INSPECT, READ)) {
    http_response_code(403);
    echo htmlspecialchars(__('Unable to inspect this Ticket.', 'clarus'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

$ticketId = filter_var($input['tickets_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$ticket = new \Ticket();
if (!is_int($ticketId) || !$ticket->getFromDB($ticketId) || !Authorization::canInspectTicket($ticket)) {
    http_response_code(403);
    echo htmlspecialchars(__('Unable to inspect this Ticket.', 'clarus'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

echo TicketTab::renderInspection($ticket, true);
