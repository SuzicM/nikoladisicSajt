<?php
declare(strict_types=1);

require_once __DIR__ . '/../../api/lib/options.php';

test('quiz_options učitava 6 pitanja', function () {
    $o = quiz_options();
    assert_same(6, count($o['questions']));
});

test('quiz_question pronalazi pitanje po id-u', function () {
    $q = quiz_question(quiz_options(), 'spremnost');
    assert_same('single', $q['type']);
});

test('quiz_question vraća null za nepoznat id', function () {
    assert_same(null, quiz_question(quiz_options(), 'nepostojece'));
});
