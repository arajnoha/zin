<?php
require 'Parsedown.php';

$CONTENT_DIR = __DIR__ . '/content';
$PUBLIC_DIR = __DIR__ . '/public';
$TEMPLATES_DIR = __DIR__ . '/templates';
$THEMES_DIR = __DIR__ . '/themes';
$POSTS_DIR = $PUBLIC_DIR . '/posts';
$TAGS_DIR = $PUBLIC_DIR . '/tags';

if (is_dir($PUBLIC_DIR)) {exec("rm -rf " . escapeshellarg($PUBLIC_DIR));}
mkdir($PUBLIC_DIR);

if (!is_dir($CONTENT_DIR)) mkdir($CONTENT_DIR, 0755, true);
if (!is_dir($POSTS_DIR)) mkdir($POSTS_DIR, 0755, true);
if (!is_dir($TAGS_DIR)) mkdir($TAGS_DIR, 0755, true);

function parseConfigFile($configFile) {
    $config = [];

    if (file_exists($configFile)) {
        $fileContent = file_get_contents($configFile);
        
        if (preg_match('/^---(.*?)---/s', $fileContent, $matches)) {
            foreach (explode("\n", trim($matches[1])) as $line) {
                if (strpos($line, ':') !== false) {
                    [$key, $value] = explode(':', $line, 2);
                    $config[trim($key)] = trim($value);
                }
            }
        }
    }

    return $config;
}

// Load global config
$config = parseConfigFile(__DIR__ . '/config.md');

// Helper to apply config data to any template
function populateTemplate($template, $config) {
    foreach ($config as $key => $value) {
        $template = str_replace("{{{$key}}}", $value, $template);
    }
    return $template;
}

// Copy theme CSS to the public directory
$themeFile = "$THEMES_DIR/{$config['color_scheme']}.css";
$globalStyles = file_get_contents("$THEMES_DIR/style.css") . "\n" . file_get_contents($themeFile);
file_put_contents("$PUBLIC_DIR/style.css", $globalStyles);
file_put_contents("$PUBLIC_DIR/favicon.svg", file_get_contents("favicon.svg"));

$postsList = "";
$allTags = [];
$parsedown = new Parsedown();

// Article processing loop
foreach (glob("$CONTENT_DIR/*.md") as $file) {
    $fileContent = file_get_contents($file);

    // Split front matter and content
    if (preg_match('/^---(.*?)---\s*(.*)$/s', $fileContent, $matches)) {
        $frontMatter = trim($matches[1]);
        $content = trim($matches[2]);

        // Parse front matter
        $frontMatterLines = explode("\n", $frontMatter);
        $title = '';
        $date = date('Y-m-d');
        $tags = [];

        foreach ($frontMatterLines as $line) {
            if (preg_match('/^title:\s*(.+)$/', $line, $titleMatch)) {
                $title = trim($titleMatch[1]);
            } elseif (preg_match('/^date:\s*(.+)$/', $line, $dateMatch)) {
                $date = trim($dateMatch[1]);
            } elseif (preg_match('/^tags:\s*(.+)$/', $line, $tagsMatch)) {
                $tags = array_map('trim', explode(',', $tagsMatch[1]));
            }
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $htmlContent = $parsedown->text($content);
        $postTemplate = file_get_contents("$TEMPLATES_DIR/post.html");

        // Populate post template with global config and post-specific data
        $postHtml = populateTemplate($postTemplate, $config);
        $postHtml = str_replace(
            ['{{title}}', '{{date}}', '{{content}}', '{{tags}}'],
            [$title, $date, $htmlContent, implode(', ', array_map(fn($tag) => "<a href='/tags/$tag.html'>#$tag</a>", $tags))],
            $postHtml
        );

        // Add new article into the archive
        file_put_contents("$POSTS_DIR/$slug.html", $postHtml);
        $postsList .= "<li><a href='/posts/$slug.html'>$title</a> - $date</li>";
        foreach ($tags as $tag) {
            $allTags[$tag][] = "<li><a href='/posts/$slug.html'>$title</a> - $date</li>";
        }
    }
}

// Populate the archive template
$archiveTemplate = file_get_contents("$TEMPLATES_DIR/archive.html");
$archiveHtml = populateTemplate($archiveTemplate, $config);
$archiveHtml = str_replace('{{posts}}', $postsList, $archiveHtml);
file_put_contents("$PUBLIC_DIR/index.html", $archiveHtml);

// Update tag pages
foreach ($allTags as $tag => $links) {
    $tagTemplate = file_get_contents("$TEMPLATES_DIR/tag.html");

    // Populate tag template with global config and tag-specific data
    $tagHtml = populateTemplate($tagTemplate, $config);
    $tagHtml = str_replace(['{{tag}}', '{{posts}}'], [$tag, implode("\n", $links)], $tagHtml);
    file_put_contents("$TAGS_DIR/$tag.html", $tagHtml);
}

echo "Site generated successfully!";