@php
    $readableNew = ucwords(str_replace('_', ' ', $newStatus));
    $readablePrevious = ucwords(str_replace('_', ' ', $previousStatus));
@endphp
@extends('emails.layout')

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
