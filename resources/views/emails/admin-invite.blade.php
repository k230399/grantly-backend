@extends('emails.layout')

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
