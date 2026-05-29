@extends('emails.layout')

@section('icon')
<img
  src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyOCIgaGVpZ2h0PSIyOCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiMyNTYzZWIiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cGF0aCBkPSJNMTYgMjF2LTJhNCA0IDAgMCAwLTQtNEg2YTQgNCAwIDAgMC00IDR2MiIvPjxjaXJjbGUgY3g9IjkiIGN5PSI3IiByPSI0Ii8+PGxpbmUgeDE9IjE5IiB4Mj0iMTkiIHkxPSI4IiB5Mj0iMTQiLz48bGluZSB4MT0iMjIiIHgyPSIxNiIgeTE9IjExIiB5Mj0iMTEiLz48L3N2Zz4="
  width="28"
  height="28"
  alt=""
  style="margin-top: 18px;"
/>
@endsection

@section('heading')You've been invited to Grantly@endsection

@section('body')
<p style="margin: 0 0 8px; font-size: 15px; line-height: 1.6; color: #6b7280; text-align: center;">
  An administrator has invited you to join Grantly as an admin. Click the button below to set your password and access the admin portal.
</p>
<p style="margin: 0 0 0; font-size: 13px; line-height: 1.6; color: #9ca3af; text-align: center;">
  This invitation expires in 24 hours. If you weren't expecting this, you can safely ignore this email.
</p>
@endsection

@section('cta')
<a
  href="{{ $inviteUrl }}"
  style="display: inline-block; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; padding: 14px 36px; border-radius: 10px; letter-spacing: -0.1px;"
>
  Accept invitation
</a>
@endsection

@section('after_cta')
Button not working? Copy and paste this link into your browser:<br />
<a href="{{ $inviteUrl }}" style="color: #2563eb; word-break: break-all;">{{ $inviteUrl }}</a>
@endsection

@section('footer_line')
You're receiving this because an administrator invited you to Grantly.
@endsection
