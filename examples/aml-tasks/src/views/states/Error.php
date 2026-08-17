<?php
namespace App\Views\States;
final class ErrorPage extends \AML\View\Page {
    public function body(): \AML\View\View { return \AML\View\Text(\App\Support\Copy::text('error'))->class('route-state', 'route-error'); }
}
