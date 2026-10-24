@props(['rows'])
<table class="details" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($rows as $row)
<tr>
<td class="details-label" width="38%">{{ $row['label'] }}</td>
<td class="details-value @if ($row['mono'] ?? false) details-mono @endif">{{ $row['value'] }}</td>
</tr>
@endforeach
</table>
