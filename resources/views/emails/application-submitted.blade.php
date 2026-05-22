@extends('emails.layout')

@section('icon')
<img
  src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyOCIgaGVpZ2h0PSIyOCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMyNTYzZWIiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMjEuODAxIDEwQTEwIDEwIDAgMSAxIDE3IDMuMzM1Ii8+PHBhdGggZD0ibTkgMTEgMyAzTDIyIDQiLz48L3N2Zz4="
  width="28"
  height="28"
  alt=""
  style="margin-top: 18px;"
/>
@endsection

@section('heading')Your application has been submitted@endsection

@section('body')
<p style="margin: 0 0 8px; font-size: 15px; line-height: 1.6; color: #6b7280; text-align: center;">
  Hi {{ $application->applicant->full_name }}, thanks for submitting your application to <strong style="color: #111827;">{{ $round->title }}</strong>. We have received it and will be in touch as it moves through review.
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
  <tr>
    <td style="padding: 4px 0; font-size: 13px; color: #6b7280;">Submitted</td>
    <td style="padding: 4px 0; font-size: 13px; color: #111827; text-align: right; font-weight: 600;">{{ $application->submitted_at?->format('j M Y, g:i a') }}</td>
  </tr>
</table>
@endsection

@section('cta')
<a
  href="{{ $viewUrl }}"
  style="display: inline-block; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 14px 36px; border-radius: 10px; letter-spacing: -0.1px;"
>
  View application
</a>
@endsection

@section('after_cta')
If you did not submit this application, please reply to this email and let us know.
@endsection
