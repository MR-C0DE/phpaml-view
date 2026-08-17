<?php
namespace App\Views\States;
final class LoadingPage extends \AML\View\Page {
    public function body(): \AML\View\View { return \AML\View\Text(\App\Support\Copy::text('loading'))->class('route-state'); }
}
