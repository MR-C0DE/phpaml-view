<?php

declare(strict_types=1);
namespace AML\View\Tests\Native;
use AML\View\{Page, State, View};
use function AML\View\Button;
final class NativeLibrary extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'case_sqrt';
    public function body(): View { return Button('Run')->onClick($this->{$this->handler}(...)); }
    public function case_acos(): void { $value = acos(0.5); $this->result = [$value, gettype($value)]; }
    public function case_acosh(): void { $value = acosh(2.0); $this->result = [$value, gettype($value)]; }
    public function case_asin(): void { $value = asin(0.5); $this->result = [$value, gettype($value)]; }
    public function case_asinh(): void { $value = asinh(2.0); $this->result = [$value, gettype($value)]; }
    public function case_atan(): void { $value = atan(1.0); $this->result = [$value, gettype($value)]; }
    public function case_atan2(): void { $value = atan2(1.0, 2.0); $this->result = [$value, gettype($value)]; }
    public function case_atanh(): void { $value = atanh(0.5); $this->result = [$value, gettype($value)]; }
    public function case_base_convert(): void { $value = base_convert('ff', 16, 2); $this->result = [$value, gettype($value)]; }
    public function case_bindec(): void { $value = bindec('101101'); $this->result = [$value, gettype($value)]; }
    public function case_cos(): void { $value = cos(1.0); $this->result = [$value, gettype($value)]; }
    public function case_cosh(): void { $value = cosh(1.0); $this->result = [$value, gettype($value)]; }
    public function case_decbin(): void { $value = decbin(-7); $this->result = [$value, gettype($value)]; }
    public function case_dechex(): void { $value = dechex(255); $this->result = [$value, gettype($value)]; }
    public function case_decoct(): void { $value = decoct(63); $this->result = [$value, gettype($value)]; }
    public function case_deg2rad(): void { $value = deg2rad(180.0); $this->result = [$value, gettype($value)]; }
    public function case_exp(): void { $value = exp(2.0); $this->result = [$value, gettype($value)]; }
    public function case_expm1(): void { $value = expm1(0.0001); $this->result = [$value, gettype($value)]; }
    public function case_fdiv(): void { $value = fdiv(7.0, 2.0); $this->result = [$value, gettype($value)]; }
    public function case_fmod(): void { $value = fmod(-7.5, 2.0); $this->result = [$value, gettype($value)]; }
    public function case_fpow(): void { $value = fpow(2.0, 3.0); $this->result = [$value, gettype($value)]; }
    public function case_hexdec(): void { $value = hexdec('aBc'); $this->result = [$value, gettype($value)]; }
    public function case_hypot(): void { $value = hypot(3.0, 4.0); $this->result = [$value, gettype($value)]; }
    public function case_is_finite(): void { $value = is_finite(fdiv(1, 0)); $this->result = [$value, gettype($value)]; }
    public function case_is_infinite(): void { $value = is_infinite(fdiv(1, 0)); $this->result = [$value, gettype($value)]; }
    public function case_is_nan(): void { $value = is_nan(sqrt(-1)); $this->result = [$value, gettype($value)]; }
    public function case_log(): void { $value = log(8, 2); $this->result = [$value, gettype($value)]; }
    public function case_log10(): void { $value = log10(100); $this->result = [$value, gettype($value)]; }
    public function case_log1p(): void { $value = log1p(0.0001); $this->result = [$value, gettype($value)]; }
    public function case_max(): void { $value = max([1, 8, 3.0]); $this->result = [$value, gettype($value)]; }
    public function case_min(): void { $value = min(4, 2.0, -3, 8); $this->result = [$value, gettype($value)]; }
    public function case_octdec(): void { $value = octdec('77'); $this->result = [$value, gettype($value)]; }
    public function case_pi(): void { $value = pi(); $this->result = [$value, gettype($value)]; }
    public function case_pow(): void { $value = pow(2, 4); $this->result = [$value, gettype($value)]; }
    public function case_rad2deg(): void { $value = rad2deg(M_PI); $this->result = [$value, gettype($value)]; }
    public function case_sin(): void { $value = sin(0.7); $this->result = [$value, gettype($value)]; }
    public function case_sinh(): void { $value = sinh(0.7); $this->result = [$value, gettype($value)]; }
    public function case_sqrt(): void { $value = sqrt(81); $this->result = [$value, gettype($value)]; }
    public function case_tan(): void { $value = tan(0.7); $this->result = [$value, gettype($value)]; }
    public function case_tanh(): void { $value = tanh(0.7); $this->result = [$value, gettype($value)]; }
    public function case_addcslashes(): void { $value = addcslashes('a' . PHP_EOL, 'a..z\\n'); $this->result = [$value, gettype($value)]; }
    public function case_addslashes(): void { $value = addslashes("O'Reilly\0\\"); $this->result = [$value, gettype($value)]; }
    public function case_bin2hex(): void { $value = bin2hex('André'); $this->result = [$value, gettype($value)]; }
    public function case_chop(): void { $value = chop('abc  '); $this->result = [$value, gettype($value)]; }
    public function case_chr(): void { $value = chr(65); $this->result = [$value, gettype($value)]; }
    public function case_chunk_split(): void { $value = chunk_split('abcdef', 2, ':'); $this->result = [$value, gettype($value)]; }
    public function case_convert_uudecode(): void { $value = convert_uudecode(convert_uuencode('André')); $this->result = [$value, gettype($value)]; }
    public function case_convert_uuencode(): void { $value = convert_uuencode('André'); $this->result = [$value, gettype($value)]; }
    public function case_count_chars(): void { $value = count_chars('abac', 1); $this->result = [$value, gettype($value)]; }
    public function case_crc32(): void { $value = crc32('hello'); $this->result = [$value, gettype($value)]; }
    public function case_get_html_translation_table(): void { $value = get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES | ENT_HTML5); $this->result = [$value, gettype($value)]; }
    public function case_hex2bin(): void { $value = hex2bin('416e6472c3a9'); $this->result = [$value, gettype($value)]; }
    public function case_html_entity_decode(): void { $value = html_entity_decode('&eacute; &amp; &#039; &#x1F600;'); $this->result = [$value, gettype($value)]; }
    public function case_htmlentities(): void { $value = htmlentities("é <a> 'x' &", ENT_QUOTES | ENT_HTML5, 'UTF-8' ); $this->result = [$value, gettype($value)]; }
    public function case_htmlspecialchars(): void { $value = htmlspecialchars("<a x='b'>&", ENT_QUOTES); $this->result = [$value, gettype($value)]; }
    public function case_htmlspecialchars_decode(): void { $value = htmlspecialchars_decode('&lt;&amp;&quot;&#039; &eacute;'); $this->result = [$value, gettype($value)]; }
    public function case_join(): void { $value = join('-', [1, 2.0, false]); $this->result = [$value, gettype($value)]; }
    public function case_levenshtein(): void { $value = levenshtein('kitten', 'sitting'); $this->result = [$value, gettype($value)]; }
    public function case_md5(): void { $value = md5('hello'); $this->result = [$value, gettype($value)]; }
    public function case_nl2br(): void { $value = nl2br("a\r\nb\nc", false); $this->result = [$value, gettype($value)]; }
    public function case_ord(): void { $value = ord('é'); $this->result = [$value, gettype($value)]; }
    public function case_quoted_printable_decode(): void { $value = quoted_printable_decode('Andr=C3=A9'); $this->result = [$value, gettype($value)]; }
    public function case_quoted_printable_encode(): void { $value = quoted_printable_encode('André'); $this->result = [$value, gettype($value)]; }
    public function case_quotemeta(): void { $value = quotemeta('a.b[c]+$'); $this->result = [$value, gettype($value)]; }
    public function case_sha1(): void { $value = sha1('hello'); $this->result = [$value, gettype($value)]; }
    public function case_similar_text(): void { $value = similar_text('abcdef', 'abcXYZ'); $this->result = [$value, gettype($value)]; }
    public function case_soundex(): void { $value = soundex('Robert'); $this->result = [$value, gettype($value)]; }
    public function case_sprintf(): void { $value = sprintf('%s:%04d:%.2f', 'A', 7, 12.345); $this->result = [$value, gettype($value)]; }
    public function case_sscanf(): void { $value = sscanf('12 André 2.5', '%d %s %f'); $this->result = [$value, gettype($value)]; }
    public function case_str_decrement(): void { $value = str_decrement('AA'); $this->result = [$value, gettype($value)]; }
    public function case_str_getcsv(): void { $value = str_getcsv( 'a,"b,c",d', ',', '"', '' ); $this->result = [$value, gettype($value)]; }
    public function case_str_increment(): void { $value = str_increment('Z9'); $this->result = [$value, gettype($value)]; }
    public function case_str_ireplace(): void { $value = str_ireplace('ab', 'x', 'ABab'); $this->result = [$value, gettype($value)]; }
    public function case_str_pad(): void { $value = str_pad('abc', 8, '-+', STR_PAD_BOTH); $this->result = [$value, gettype($value)]; }
    public function case_str_repeat(): void { $value = str_repeat('é', 3); $this->result = [$value, gettype($value)]; }
    public function case_str_rot13(): void { $value = str_rot13('Hello World'); $this->result = [$value, gettype($value)]; }
    public function case_str_shuffle(): void { $value = str_shuffle('a'); $this->result = [$value, gettype($value)]; }
    public function case_str_split(): void { $value = str_split('abcdef', 2); $this->result = [$value, gettype($value)]; }
    public function case_str_word_count(): void { $value = str_word_count("Hello, it's a-test!", 2); $this->result = [$value, gettype($value)]; }
    public function case_strcasecmp(): void { $value = strcasecmp('Alpha', 'alpha'); $this->result = [$value, gettype($value)]; }
    public function case_strchr(): void { $value = strchr('abcabc', 'bc', true); $this->result = [$value, gettype($value)]; }
    public function case_strcmp(): void { $value = strcmp('abc', 'abd'); $this->result = [$value, gettype($value)]; }
    public function case_strcspn(): void { $value = strcspn('abcdef', 'dx', 1); $this->result = [$value, gettype($value)]; }
    public function case_strip_tags(): void { $value = strip_tags('<p>Hello <b>world</b><!--x--></p>', '<b>'); $this->result = [$value, gettype($value)]; }
    public function case_stripcslashes(): void { $value = stripcslashes('a\\n\\x41\\101'); $this->result = [$value, gettype($value)]; }
    public function case_stripos(): void { $value = stripos('AbCdAb', 'ab', 1); $this->result = [$value, gettype($value)]; }
    public function case_stripslashes(): void { $value = stripslashes("a\\'b\\0"); $this->result = [$value, gettype($value)]; }
    public function case_stristr(): void { $value = stristr('AbCde', 'CD'); $this->result = [$value, gettype($value)]; }
    public function case_strnatcasecmp(): void { $value = strnatcasecmp('File10', 'file2'); $this->result = [$value, gettype($value)]; }
    public function case_strnatcmp(): void { $value = strnatcmp('file2', 'file10'); $this->result = [$value, gettype($value)]; }
    public function case_strncasecmp(): void { $value = strncasecmp('ABCx', 'abcy', 3); $this->result = [$value, gettype($value)]; }
    public function case_strncmp(): void { $value = strncmp('abcdef', 'abcxyz', 3); $this->result = [$value, gettype($value)]; }
    public function case_strpbrk(): void { $value = strpbrk('abcdef', 'dx'); $this->result = [$value, gettype($value)]; }
    public function case_strpos(): void { $value = strpos('éabcé', 'a'); $this->result = [$value, gettype($value)]; }
    public function case_strrchr(): void { $value = strrchr('abac', 'a'); $this->result = [$value, gettype($value)]; }
    public function case_strrev(): void { $value = strrev('abc'); $this->result = [$value, gettype($value)]; }
    public function case_strripos(): void { $value = strripos('aBaB', 'AB', -1); $this->result = [$value, gettype($value)]; }
    public function case_strrpos(): void { $value = strrpos('ababa', 'ba', 1); $this->result = [$value, gettype($value)]; }
    public function case_strspn(): void { $value = strspn('123abc', '0123456789'); $this->result = [$value, gettype($value)]; }
    public function case_strstr(): void { $value = strstr('abcabc', 'bc'); $this->result = [$value, gettype($value)]; }
    public function case_strtok(): void { $value = strtok('a b', ' '); $this->result = [$value, gettype($value)]; }
    public function case_strtr(): void { $value = strtr('abab', ['ab' => 'X', 'a' => 'Y']); $this->result = [$value, gettype($value)]; }
    public function case_substr_compare(): void { $value = substr_compare('abcdef', 'cde', 2, 3); $this->result = [$value, gettype($value)]; }
    public function case_substr_count(): void { $value = substr_count('ababab', 'ab', 1, 4); $this->result = [$value, gettype($value)]; }
    public function case_substr_replace(): void { $value = substr_replace('abcdef', 'X', -3, -1); $this->result = [$value, gettype($value)]; }
    public function case_vsprintf(): void { $value = vsprintf('%s %d', ['hello', 7]); $this->result = [$value, gettype($value)]; }
    public function case_wordwrap(): void { $value = wordwrap('hello world again', 7, '|', false); $this->result = [$value, gettype($value)]; }
    public function case_array_all(): void { $value = array_all([2, 4], fn ($v, $k) => $v % 2 === 0); $this->result = [$value, gettype($value)]; }
    public function case_array_any(): void { $value = array_any([1, 4], fn ($v, $k) => $v % 2 === 0); $this->result = [$value, gettype($value)]; }
    public function case_array_change_key_case(): void { $value = array_change_key_case(['A' => 1, 'B' => 2], CASE_LOWER); $this->result = [$value, gettype($value)]; }
    public function case_array_chunk(): void { $value = array_chunk(['a' => 1, 8 => 2, 'b' => 3], 2, true); $this->result = [$value, gettype($value)]; }
    public function case_array_column(): void { $value = array_column([['id' => 'x', 'v' => 2], ['id' => 'y', 'v' => 3]], 'v', 'id'); $this->result = [$value, gettype($value)]; }
    public function case_array_combine(): void { $value = array_combine(['a', 'b'], [1.0, 2]); $this->result = [$value, gettype($value)]; }
    public function case_array_count_values(): void { $value = array_count_values(['1', 1, 'x', 'x']); $this->result = [$value, gettype($value)]; }
    public function case_array_diff(): void { $value = array_diff([1, 2, '3'], ['2', 9]); $this->result = [$value, gettype($value)]; }
    public function case_array_diff_assoc(): void { $value = array_diff_assoc(['x' => 1, 'y' => 2], ['x' => '1']); $this->result = [$value, gettype($value)]; }
    public function case_array_diff_key(): void { $value = array_diff_key(['x' => 1, 'y' => 2], ['x' => 9]); $this->result = [$value, gettype($value)]; }
    public function case_array_diff_uassoc(): void { $value = array_diff_uassoc(['A' => 1, 'B' => 2], ['a' => '1'], 'strcasecmp'); $this->result = [$value, gettype($value)]; }
    public function case_array_diff_ukey(): void { $value = array_diff_ukey(['A' => 1, 'B' => 2], ['a' => 9], 'strcasecmp'); $this->result = [$value, gettype($value)]; }
    public function case_array_fill(): void { $value = array_fill(-2, 4, 1.0); $this->result = [$value, gettype($value)]; }
    public function case_array_fill_keys(): void { $value = array_fill_keys(['a', 2, 'b'], 1.0); $this->result = [$value, gettype($value)]; }
    public function case_array_filter(): void { $value = array_filter(['a' => 2, 'b' => 3], fn ($v, $k) => $v > 2 && $k === 'b', ARRAY_FILTER_USE_BOTH); $this->result = [$value, gettype($value)]; }
    public function case_array_find(): void { $value = array_find(['a' => 2, 'b' => 3], fn ($v, $k) => $k === 'b'); $this->result = [$value, gettype($value)]; }
    public function case_array_find_key(): void { $value = array_find_key(['a' => 2, 'b' => 3], fn ($v, $k) => $v === 3); $this->result = [$value, gettype($value)]; }
    public function case_array_flip(): void { $value = array_flip(['x' => 1, 'y' => 'a', 'z' => 1]); $this->result = [$value, gettype($value)]; }
    public function case_array_intersect(): void { $value = array_intersect([1, 2, '3'], ['2', 3]); $this->result = [$value, gettype($value)]; }
    public function case_array_intersect_assoc(): void { $value = array_intersect_assoc(['x' => 1, 'y' => 2], ['x' => '1']); $this->result = [$value, gettype($value)]; }
    public function case_array_intersect_key(): void { $value = array_intersect_key(['x' => 1, 'y' => 2], ['x' => 9]); $this->result = [$value, gettype($value)]; }
    public function case_array_intersect_uassoc(): void { $value = array_intersect_uassoc(['A' => 1, 'B' => 2], ['a' => '1'], 'strcasecmp'); $this->result = [$value, gettype($value)]; }
    public function case_array_intersect_ukey(): void { $value = array_intersect_ukey(['A' => 1, 'B' => 2], ['a' => 9], 'strcasecmp'); $this->result = [$value, gettype($value)]; }
    public function case_array_is_list(): void { $value = array_is_list([1, 2, 3]); $this->result = [$value, gettype($value)]; }
    public function case_array_key_exists(): void { $value = array_key_exists('x', ['x' => null]); $this->result = [$value, gettype($value)]; }
    public function case_array_key_first(): void { $value = array_key_first([2 => 'b', 'a' => 'a']); $this->result = [$value, gettype($value)]; }
    public function case_array_key_last(): void { $value = array_key_last([2 => 'b', 'a' => 'a']); $this->result = [$value, gettype($value)]; }
    public function case_array_keys(): void { $value = array_keys([1, 1.0, '1', 2], 1, true); $this->result = [$value, gettype($value)]; }
    public function case_array_map(): void { $value = array_map(fn ($a, $b) => $a + $b, [1, 2], [10, 20]); $this->result = [$value, gettype($value)]; }
    public function case_array_merge(): void { $value = array_merge([2 => 1, 'a' => 2], [9 => 3, 'a' => 4]); $this->result = [$value, gettype($value)]; }
    public function case_array_merge_recursive(): void { $value = array_merge_recursive(['a' => 1, 8 => 'x'], ['a' => 2, 9 => 'y']); $this->result = [$value, gettype($value)]; }
    public function case_array_pad(): void { $value = array_pad(['a' => 1, 9 => 2], -4, 0); $this->result = [$value, gettype($value)]; }
    public function case_array_product(): void { $value = array_product([2, 3.0, '4']); $this->result = [$value, gettype($value)]; }
    public function case_array_rand(): void { $value = array_rand(['x' => 1], 1); $this->result = [$value, gettype($value)]; }
    public function case_array_reduce(): void { $value = array_reduce([1, 2, 3], fn ($sum, $v) => $sum + $v, 5); $this->result = [$value, gettype($value)]; }
    public function case_array_replace(): void { $value = array_replace([2 => 'a', 'x' => 2], [2 => 'b', 'y' => 3]); $this->result = [$value, gettype($value)]; }
    public function case_array_replace_recursive(): void { $value = array_replace_recursive(['x' => ['a' => 1, 'b' => 2]], ['x' => ['b' => 3]]); $this->result = [$value, gettype($value)]; }
    public function case_array_reverse(): void { $value = array_reverse(['a' => 1, 8 => 2, 4 => 3], true); $this->result = [$value, gettype($value)]; }
    public function case_array_search(): void { $value = array_search('2', ['x' => 2, 'y' => '2'], false); $this->result = [$value, gettype($value)]; }
    public function case_array_slice(): void { $value = array_slice(['a' => 1, 8 => 2, 4 => 3], -2, 1, true); $this->result = [$value, gettype($value)]; }
    public function case_array_sum(): void { $value = array_sum([1, 2.0, '3']); $this->result = [$value, gettype($value)]; }
    public function case_array_udiff(): void { $value = array_udiff([1, 2, 3], [2], fn ($a, $b) => $a <=> $b); $this->result = [$value, gettype($value)]; }
    public function case_array_udiff_assoc(): void { $value = array_udiff_assoc(['x' => 1, 'y' => 2], ['x' => 1], fn ($a, $b) => $a <=> $b); $this->result = [$value, gettype($value)]; }
    public function case_array_udiff_uassoc(): void { $value = array_udiff_uassoc(['X' => 1, 'Y' => 2], ['x' => 1], fn ($a, $b) => $a <=> $b, 'strcasecmp'); $this->result = [$value, gettype($value)]; }
    public function case_array_uintersect(): void { $value = array_uintersect([1, 2, 3], [2], fn ($a, $b) => $a <=> $b); $this->result = [$value, gettype($value)]; }
    public function case_array_uintersect_assoc(): void { $value = array_uintersect_assoc(['x' => 1, 'y' => 2], ['x' => 1], fn ($a, $b) => $a <=> $b); $this->result = [$value, gettype($value)]; }
    public function case_array_uintersect_uassoc(): void { $value = array_uintersect_uassoc(['X' => 1, 'Y' => 2], ['x' => 1], fn ($a, $b) => $a <=> $b, 'strcasecmp'); $this->result = [$value, gettype($value)]; }
    public function case_array_unique(): void { $value = array_unique([1, 1.0, '1', 2], SORT_REGULAR); $this->result = [$value, gettype($value)]; }
    public function case_array_values(): void { $value = array_values(['x' => 1.0, 'y' => 2]); $this->result = [$value, gettype($value)]; }
    public function case_count(): void { $value = count([1, [2, [3]]], COUNT_RECURSIVE); $this->result = [$value, gettype($value)]; }
    public function case_current(): void { $value = current([2 => 1, 0 => 3]); $this->result = [$value, gettype($value)]; }
    public function case_in_array(): void { $value = in_array('2', [1, 2], false); $this->result = [$value, gettype($value)]; }
    public function case_key(): void { $value = key([2 => 1, 0 => 3]); $this->result = [$value, gettype($value)]; }
    public function case_key_exists(): void { $value = key_exists('x', ['x' => null]); $this->result = [$value, gettype($value)]; }
    public function case_pos(): void { $value = pos([2 => 1, 0 => 3]); $this->result = [$value, gettype($value)]; }
    public function case_range(): void { $value = range('a', 'e', 2); $this->result = [$value, gettype($value)]; }
    public function case_sizeof(): void { $value = sizeof([1, 2]); $this->result = [$value, gettype($value)]; }
    public function case_sort(): void { $a = ['z' => 3, 'a' => 1.0, 4 => 2]; $ok = sort($a, SORT_REGULAR); $this->result = [$ok, $a]; }
    public function case_rsort(): void { $a = ['z' => 3, 'a' => 1.0, 4 => 2]; $ok = rsort($a, SORT_REGULAR); $this->result = [$ok, $a]; }
    public function case_asort(): void { $a = ['z' => 3, 'a' => 1.0, 4 => 2]; $ok = asort($a, SORT_REGULAR); $this->result = [$ok, $a]; }
    public function case_arsort(): void { $a = ['z' => 3, 'a' => 1.0, 4 => 2]; $ok = arsort($a, SORT_REGULAR); $this->result = [$ok, $a]; }
    public function case_ksort(): void { $a = ['z' => 3, 'a' => 1.0, 4 => 2]; $ok = ksort($a, SORT_REGULAR); $this->result = [$ok, $a]; }
    public function case_krsort(): void { $a = ['z' => 3, 'a' => 1.0, 4 => 2]; $ok = krsort($a, SORT_REGULAR); $this->result = [$ok, $a]; }
    public function case_usort(): void { $a = ['z' => 3, 'a' => 1.0, 'b' => 2]; $ok = usort($a, fn ($a, $b) => $a <=> $b); $this->result = [$ok, $a]; }
    public function case_uasort(): void { $a = ['z' => 3, 'a' => 1.0, 'b' => 2]; $ok = uasort($a, fn ($a, $b) => $a <=> $b); $this->result = [$ok, $a]; }
    public function case_uksort(): void { $a = ['z' => 3, 'a' => 1.0, 'b' => 2]; $ok = uksort($a, fn ($a, $b) => $a <=> $b); $this->result = [$ok, $a]; }
    public function case_natsort(): void { $a = ['x' => 'File10', 'y' => 'file2', 'z' => 'File1']; $ok = natsort($a); $this->result = [$ok, $a]; }
    public function case_natcasesort(): void { $a = ['x' => 'File10', 'y' => 'file2', 'z' => 'File1']; $ok = natcasesort($a); $this->result = [$ok, $a]; }
    public function case_array_pop(): void { $a = [2 => 3, 'a' => 1.0, 5 => 2]; $v = array_pop($a); $a[] = 9; $this->result = [$v, $a]; }
    public function case_array_shift(): void { $a = [2 => 3, 'a' => 1.0, 5 => 2]; $v = array_shift($a); $a[] = 9; $this->result = [$v, $a]; }
    public function case_array_push(): void { $a = [2 => 3, 'a' => 1.0, 5 => 2]; $v = array_push($a, 8, 9.0); $this->result = [$v, $a]; }
    public function case_array_unshift(): void { $a = [2 => 3, 'a' => 1.0, 5 => 2]; $v = array_unshift($a, 8, 9.0); $this->result = [$v, $a]; }
    public function case_array_splice(): void { $a = ['a' => 1, 8 => 2, 9 => 3]; $v = array_splice($a, 1, 1, [7.0, 8]); $this->result = [$v, $a]; }
    public function case_array_multisort(): void { $a = [2, 1, 2]; $b = ['z', 'b', 'a']; $v = array_multisort($a, SORT_ASC, $b, SORT_DESC); $this->result = [$v, $a, $b]; }
    public function case_array_walk(): void { $a = ['x' => 1, 'y' => 2]; $ok = array_walk($a, function (&$v, $k, $factor): void { $v *= $factor; }, 3); $this->result = [$ok, $a]; }
    public function case_array_walk_recursive(): void { $a = ['x' => [1, 2], 'y' => 3]; $ok = array_walk_recursive($a, function (&$v, $k): void { $v *= 2; }); $this->result = [$ok, $a]; }
    public function case_pointers(): void { $a = ['x' => 1, 'y' => 2]; $this->result = [current($a), next($a), key($a), next($a), current($a), prev($a), reset($a), end($a), prev($a)]; }
    public function case_printf(): void { $v = printf('%s:%02d', 'a', 3); $this->result = [$v]; }
    public function case_vprintf(): void { $v = vprintf('%s:%02d', ['a', 3]); $this->result = [$v]; }
    public function case_parse_str(): void { parse_str('a=1&list[]=x&list[]=y&user[name]=Andr%C3%A9', $data); $this->result = [$data]; }
    public function case_similarity_ref(): void { $n = similar_text('abc', 'abd', $percent); $this->result = [$n, $percent, gettype($percent)]; }
    public function case_scan_ref(): void { $n = sscanf('42 André', '%d %s', $number, $name); $this->result = [$n, $number, $name]; }
    public function case_first_class(): void { $fn = strtoupper(...); $this->result = [$fn('abc'), array_map($fn, ['a', 'b']), gettype($fn)]; }
    public function case_map_null(): void { $this->result = [array_map(null, [1, 2], ['a']), array_map(null, ['x' => 3])]; }
    public function case_filter_key(): void { $this->result = [array_filter(['a' => 1, 'b' => 2], fn ($key) => $key === 'b', ARRAY_FILTER_USE_KEY)]; }
    public function case_empty_merge(): void { $this->result = [array_merge(), array_merge([2 => 'x']), array_replace([])]; }
    public function case_named_math(): void { $this->result = [atan2(x: 2.0, y: 1.0), log(base: 2, num: 8), substr_count(length: 4, offset: 1, haystack: 'ababab', needle: 'ab')]; }
    public function case_alias_ref(): void { $a = [3, 1, 2]; sort(array: $a); $this->result = [$a]; }
    public function case_nested_array(): void { $this->result = [array_merge_recursive(['a' => ['x' => 1]], ['a' => ['x' => 2, 'y' => 3]]), array_column([['id' => 'a', 'n' => 2], ['n' => 3]], null, 'id')]; }
    public function case_utf8_search(): void { $this->result = [strpos('é😀abcé', 'a'), strrpos('é😀abcé', 'é'), substr_count('ééé', 'é'), str_split('éé', 2)]; }
    public function case_format_more(): void { $this->result = [sprintf('%2$s %1$04d', 7, 'ok'), sprintf('%+08.2f', -1.25), sprintf('%e %g %X', 0.00125, 12345.0, 255), sprintf('%*.*f', 8, 2, 3.14159)]; }
    public function case_invalid_array(): void { $this->counter++; $this->result = [array_chunk([], 0)]; }
    public function case_invalid_math(): void { $this->counter++; $this->result = [log(2, -1)]; }
    public function case_invalid_string(): void { $this->counter++; $this->result = [strpos('abc', 'a', 9)]; }
    public function case_invalid_callback(): void { $this->counter++; $this->result = [array_map(42, [])]; }
    public function case_invalid_reference(): void { $this->counter++; $this->result = [sort([3, 2, 1])]; }
    public function case_token_sequence(): void { $a = strtok('a,b;c', ',;'); $b = strtok(',;'); $c = strtok(',;'); $d = strtok(',;'); $this->result = [$a, $b, $c, $d]; }
    public function case_callback_weak(): void { $this->result = [array_map('strlen', [12, 345]), array_map(fn (int $x): int => $x + 1, ['2', '3'])]; }
    public function case_callback_byref(): void { $a = [1, 2]; array_walk($a, function (&$v, $key, $factor): void { $v *= $factor; }, 3); $this->result = [$a]; }
    public function case_named_reference(): void { $n = 0; $v = str_replace(subject: 'aaa', count: $n, replace: 'b', search: 'a'); $this->result = [$v, $n]; }
    public function case_spread_reference(): void { $args = [[3, 1, 2]]; sort(...$args); $this->result = [$args]; }
    public function case_firstclass_reference(): void { $f = sort(...); $a = [3, 2, 1]; $f($a); $this->result = [$a]; }
    public function case_local_nonfinite(): void { $nan = sqrt(-1); $inf = fdiv(1, 0); $this->result = [is_nan($nan), is_infinite($inf), is_nan(round($nan)), is_infinite(round($inf)), number_format($nan), number_format($inf), sprintf('%+f %+e %+g', $nan, $inf, -$inf)]; }
    public function case_numeric_strings(): void { $this->result = [array_sum(['apple', '3e2', '4.5oops']), array_product(['apple']), array_sum(['nope']), array_product(['2', '3']), array_sum([true, null, false])]; }
    public function case_search_offsets(): void { $this->result = [strrpos('ababab', 'ab', -1), strrpos('ababab', 'ab', -3), strpos('ababab', '', -1), strrpos('ababab', '', -1), stripos('aBAbaB', 'AB', 1), strripos('aBAbaB', 'AB', -3)]; }
    public function case_substr_comparisons(): void { $this->result = [substr_compare('abc', '', 3), substr_compare('abc', 'a', -20, 1), substr_compare('', '', 0, 0), substr_compare('abc', 'ABC', 0, null, true)]; }
    public function case_unicode_escapes(): void { $this->result = [addcslashes('André', 'A..C'), stripcslashes('Andr\\303\\251'), addcslashes('é', "\xc3\xa9")]; }
    public function case_scan_float(): void { $this->result = [sscanf('3.0 4', '%f %d'), sscanf('abc', '%d %s'), sscanf('', '%d'), sscanf('AB123!', '%2c%3d%n')]; }
    public function case_scan_partial_reference(): void { $a = 10; $b = 'old'; $n = sscanf('3 nope', '%d %d', $a, $b); $this->result = [$a, $b, $n]; }
    public function case_format_large(): void { $this->result = [sprintf('%.2f', 1e21), sprintf('%g %g %g', 0.000001, 1234567, 0.0001), sprintf('%u %x', -1, -1)]; }
    public function case_array_pointer_mutation(): void { $a = [10, 20, 30]; next($a); unset($a[0]); $x = current($a); unset($a[1]); $y = current($a); $this->result = [$x, $y, key($a)]; }
    public function case_array_keys_null(): void { $this->result = [array_keys(['a' => null, 'b' => false, 'c' => 0], null), array_keys(['a' => null, 'b' => false], null, true)]; }
    public function case_array_keys_numeric(): void { $this->result = [array_change_key_case(['ABC' => 1, 'abc' => 2], CASE_LOWER), array_merge_recursive([4 => 'a', 'x' => ['n' => 1]], [5 => 'b', 'x' => ['n' => 2]])]; }
    public function case_sort_flags(): void { $a = ['img12', 'img2', 'Img1', 'img02']; sort($a, SORT_NATURAL | SORT_FLAG_CASE); $b = [3, '10', '2']; sort($b, SORT_STRING); $this->result = [$a, $b]; }
    public function case_range_numeric_strings(): void { $this->result = [range('1e1', '12'), range('1.0', '3.0'), range(3, 1, -1), range(1, 1, 9)]; }
    public function case_word_ranges(): void { $this->result = [str_word_count('abc123 test-foo 456', 1, '0..9'), str_word_count('-abc- a--b', 2)]; }
    public function case_csv_whitespace(): void { $this->result = [str_getcsv('  "a", "b" ,c', ',', '"', ''), str_getcsv('"a"tail,b', ',', '"', ''), str_getcsv('', ',', '"', '')]; }
    public function case_parse_query(): void { parse_str('a.b=x&a+b=y&bad=%zz&x%5B2%5D=a&x%5B%5D=b', $data); $this->result = [$data]; }
    public function case_entity_modes(): void { $this->result = [htmlentities("' & < > é €", ENT_QUOTES | ENT_HTML5), htmlspecialchars('&amp; &bogus;', ENT_QUOTES, 'UTF-8', false), html_entity_decode('&#0; &#xD800; &apos; &amp;', ENT_QUOTES | ENT_HTML5)]; }
    public function case_invalid_named(): void { $this->counter++; $this->result = [strlen(unknown: 'abc')]; }
    public function case_invalid_default(): void { $this->counter++; $this->result = [array_filter([], 42)]; }
    public function case_invalid_arity(): void { $this->counter++; $this->result = [sqrt()]; }
    public function case_invalid_variadic_callback(): void { $this->counter++; $this->result = [array_udiff([], [], 42)]; }
    public function case_metaphone(): void { $this->result = [array_map('metaphone', ['programming', 'programmer', 'André', 'Asterix', 'school', 'night', 'knight', 'xylophone', 'whale', 'wren', 'aeon', 'dumbbell', 'resigned', 'cough', 'rough', 'through', '123']), metaphone('Asterix', 5), metaphone("ab\0cd")]; }
    public function case_conversion_callbacks(): void { $this->result = [array_map('intval', ['12', '4.5']), array_map('floatval', ['2', '3.5']), array_map('gettype', [1, 1.0, false, null, []]), array_filter([1, '2', 3.0], 'is_int'), intval('ff', 16), intval('0b101', 0), intval('077', 0)]; }
    public function case_entity_aliases(): void { $this->result = [html_entity_decode('&NonBreakingSpace;&AMP;&QUOT;&NotEqualTilde;', ENT_QUOTES | ENT_HTML5), html_entity_decode('&#1;&#13;&#128;&#65534;', ENT_QUOTES | ENT_HTML5), html_entity_decode('&#1;&#13;&#128;&#65534;', ENT_QUOTES | ENT_XML1), htmlspecialchars('&AMP;', ENT_QUOTES | ENT_HTML5, 'UTF-8', false)]; }
    public function case_pow_conversions(): void { $this->result = [pow('2', '3'), pow('2.0', '3'), pow(null, null), pow(true, 3), pow(1, sqrt(-1)), pow(-1, fdiv(1, 0)), pow(4, -0.5)]; }
    public function case_pow_invalid(): void { $this->counter++; $this->result = [pow([], 2)]; }
    public function case_walk_arity(): void { $a = [1]; array_walk($a, function (&$v, $k, ...$rest): void { $v = count($rest); }); $b = [1]; array_walk($b, function (&$v, $k, ...$rest): void { $v = count($rest); }, null); $this->result = [$a, $b]; }
    public function unsupportedLocale(): void { $this->result = [strcoll('a', 'b')]; }
    public function case_compact(): void { $a = 1; $this->result = [compact('a')]; }
    public function unsupportedEncoding(): void { $this->result = [hebrev('abc')]; }
    public function unsupportedFileHash(): void { $this->result = [md5_file('server.txt')]; }
    public function case_range_boundaries(): void { $this->result = [range(1, 3, 1.0), range(1.0, 3), range('a', 3), range('a', '3'), range('a', 'c', 0.5), range('a', 'c', 1.0), range('', 'b'), range('', '')]; }
    public function case_range_invalid_step(): void { $this->counter++; $this->result = [range(1, 3, -1)]; }
}
