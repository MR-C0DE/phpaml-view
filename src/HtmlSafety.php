<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Validate browser-interpreted values before HTML escaping. */
final class HtmlSafety
{
    public static function url(string $tag, string $attribute, string $value): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new \InvalidArgumentException('An HTML URL cannot contain control characters.');
        }
        $value = trim($value);
        if ($tag === 'script' && $attribute === 'src') return self::scriptSource($value);
        if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $match)) return $value;
        $scheme = strtolower($match[1]);
        $allowed = ['http', 'https'];
        if ($attribute === 'href' && in_array($tag, ['a', 'area'], true)) $allowed = [...$allowed, 'mailto', 'tel'];
        // Data/blob URLs are useful for image/audio/video previews, not active documents.
        $media = $attribute === 'src' && in_array($tag, ['img', 'audio', 'video', 'source', 'track'], true)
            || $attribute === 'poster' && $tag === 'video'
            || in_array($attribute, ['href', 'xlink:href'], true) && $tag === 'image';
        if ($media && $scheme === 'blob') return $value;
        if ($media && $scheme === 'data') {
            $image = in_array($tag, ['img', 'image'], true) || $attribute === 'poster';
            $mime = $image ? 'image/[a-z0-9.+-]+' : '(?:image|audio|video)/[a-z0-9.+-]+|text/vtt';
            if (preg_match('~^data:(?:' . $mime . ')(?:;[^,]*)?,~i', $value)) return $value;
        }
        if (!in_array($scheme, $allowed, true)) {
            throw new \InvalidArgumentException('Unsafe or unsupported HTML URL protocol.');
        }
        return $value;
    }

    public static function scriptSource(string $source): string
    {
        if ($source === '' || preg_match('/[\\\\\x00-\x1F\x7F]/', $source)) {
            throw new \InvalidArgumentException('A script source must be a safe local path.');
        }
        if (str_starts_with($source, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $source)) {
            throw new \InvalidArgumentException('Script() accepts local project files only.');
        }
        if (preg_match('#(?:^|/)\.\.(?:/|$)#', $source)) {
            throw new \InvalidArgumentException('A script source cannot traverse outside the public directory.');
        }
        return $source;
    }
}
