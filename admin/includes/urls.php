<?php
/**
 * Central admin URLs — always use index.php?csnsa=...
 */
function admin_url(string $page, array $query = []): string
{
    $params = array_merge(['csnsa' => $page], $query);
    return 'index.php?' . http_build_query($params);
}

function admin_asset(string $path): string
{
    return ltrim($path, '/');
}
