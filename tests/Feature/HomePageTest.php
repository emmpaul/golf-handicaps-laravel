<?php

test('the home page responds', function () {
    $this->get('/')->assertOk();
});
