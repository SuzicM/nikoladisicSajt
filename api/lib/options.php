<?php
declare(strict_types=1);

function quiz_options(?string $path = null): array
{
    static $cache = [];
    $path ??= dirname(__DIR__, 2) . '/assets/data/quiz.json';
    if (!isset($cache[$path])) {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('quiz.json nije pronađen');
        }
        $cache[$path] = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    }
    return $cache[$path];
}

function quiz_question(array $options, string $id): ?array
{
    foreach ($options['questions'] as $question) {
        if ($question['id'] === $id) {
            return $question;
        }
    }
    return null;
}
