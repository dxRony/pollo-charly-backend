@props(['url'])
<tr>
<td class="header">
<table align="center" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding-right: 12px; vertical-align: middle;">
<a href="{{ $url }}" style="display: inline-block;"><img src="{{ rtrim((string) config('app.frontend_url'), '/') }}/icon-192.png" class="logo" width="52" height="52" alt="Pollo Charly"></a>
</td>
<td class="brand-name"><a href="{{ $url }}">Pollo Charly</a></td>
</tr>
</table>
</td>
</tr>
