<?php

test('the root route redirects to filament superadmin login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/pf-admin/login');
});
