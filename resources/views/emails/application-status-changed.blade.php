@php
    $readableNew = ucwords(str_replace('_', ' ', $newStatus));
    $readablePrevious = ucwords(str_replace('_', ' ', $previousStatus));
@endphp
@extends('emails.layout')

@section('icon')
<img
  src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyOCIgaGVpZ2h0PSIyOCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMyNTYzZWIiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMyAxMmE5IDkgMCAwIDEgOS05IDkuNzUgOS43NSAwIDAgMSA2Ljc0IDIuNzRMMjEgOCIvPjxwYXRoIGQ9Ik0yMSAzdjVoLTUiLz48cGF0aCBkPSJNMjEgMTJhOSA5IDAgMCAxLTkgOSA5Ljc1IDkuNzUgMCAwIDEtNi43NC0yLjc0TDMgMTYiLz48cGF0aCBkPSJNOCAxNkgzdjUiLz48L3N2Zz4="
  width="28"
  height="28"
  alt=""
  style="margin-top: 18px;"
/>
@endsection

@section('heading')Your application is now {{ $readableNew }}@endsection

@section('body')
<p style="margin: 0 0 8px; font-size: 15px; line-height: 1.6; color: #6b7280; text-align: center;">
  Hi {{ $application->applicant->full_name }}, your application for <strong style="color: #111827;">{{ $round->title }}</strong> has moved from {{ $readablePrevious }} to <strong style="color: #111827;">{{ $readableNew }}</strong>.
</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 24px 0 0; background-color: #f9fafb; border-radius: 10px; padding: 16px 20px;">
  <tr>
    <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">Reference</td>
    <td style="padding: 4px 0; font-size: 13px; color: #111827; text-align: right; font-weight: 600;">{{ $application->reference_number }}</td>
  </tr>
  <tr>
    <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">Project</td>
    <td style="padding: 4px 0; font-size: 13px; color: #111827; text-align: right; font-weight: 600;">{{ $application->project_name }}</td>
  </tr>
</table>

@if ($notes)
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 16px 0 0; background-color: #eff6ff; border-left: 3px solid #2563eb; border-radius: 6px; padding: 14px 18px;">
  <tr>
    <td style="font-size: 12px; color: #1d4ed8; font-weight: 600; padding-bottom: 6px; letter-spacing: 0.3px; text-transform: uppercase;">
      Note from the review team
    </td>
  </tr>
  <tr>
    <td style="font-size: 14px; color: #1e3a8a; line-height: 1.5;">
      {{ $notes }}
    </td>
  </tr>
</table>
@endif
@endsection

@section('cta')
<a
  href="{{ $viewUrl }}"
  style="display: inline-block; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 14px 36px; border-radius: 10px; letter-spacing: -0.1px;"
>
  View application
</a>
@endsection
