{{-- Shared Grantly email shell. Matches frontend/email-templates/confirm-email.html so
     Supabase signup emails and Laravel transactional emails feel like the same product.
     Inline styles only, table-based layout, for maximum email-client compatibility.

     Child views provide:
       @section('icon')    — optional. If omitted, the circular icon badge is not rendered.
       @section('heading') — H1 text (string)
       @section('body')    — main body HTML (paragraphs, key/value rows, etc.)
       @section('cta')     — the primary button (full <a> tag, styled inline)
       @section('after_cta') — optional secondary text under the button
       @section('footer_line') — optional override of the bottom-line copy
--}}
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('heading') · Grantly</title>
  </head>
  <body style="margin: 0; padding: 0; background-color: #f0f5ff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f0f5ff; padding: 40px 16px;">
      <tr>
        <td align="center">

          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 520px; background-color: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden;">

            {{-- Header band --}}
            <tr>
              <td style="background-color: #2563eb; padding: 28px 40px;">
                <table cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td>
                      <table cellpadding="0" cellspacing="0" border="0">
                        <tr>
                          <td style="background-color: #fff; width: 36px; height: 36px; border-radius: 8px; text-align: center; vertical-align: middle; padding: 4px;">
                            <img
                              src="https://vyiwzdudfgiqssczgceu.supabase.co/storage/v1/object/public/grantly/images/grantly-logo.png"
                              width="28"
                              height="28"
                              alt="Grantly Logo"
                            />
                          </td>
                          <td style="padding-left: 10px; vertical-align: middle;">
                            <span style="color: #ffffff; font-size: 20px; font-weight: 600; letter-spacing: -0.3px;">Grantly</span>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            {{-- Body --}}
            <tr>
              <td style="padding: 40px 40px 32px;">

                @hasSection('icon')
                  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 28px;">
                    <tr>
                      <td align="center">
                        <div style="width: 64px; height: 64px; background-color: #dbeafe; border-radius: 50%; display: inline-block; text-align: center; line-height: 64px;">
                          @yield('icon')
                        </div>
                      </td>
                    </tr>
                  </table>
                @endif

                <h1 style="margin: 0 0 12px; font-size: 22px; font-weight: 700; color: #111827; text-align: center; letter-spacing: -0.3px;">
                  @yield('heading')
                </h1>

                @yield('body')

                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 32px 0;">
                  <tr>
                    <td align="center">
                      @yield('cta')
                    </td>
                  </tr>
                </table>

                @hasSection('after_cta')
                  <p style="margin: 0; font-size: 12px; color: #9ca3af; text-align: center; line-height: 1.6;">
                    @yield('after_cta')
                  </p>
                @endif

              </td>
            </tr>

            {{-- Divider --}}
            <tr>
              <td style="padding: 0 40px;">
                <div style="border-top: 1px solid #f3f4f6;"></div>
              </td>
            </tr>

            {{-- Footer --}}
            <tr>
              <td style="padding: 24px 40px 32px;">
                <p style="margin: 0; font-size: 12px; color: #9ca3af; text-align: center; line-height: 1.6;">
                  Grantly. Community Grant Application Portal (Australia).<br />
                  @hasSection('footer_line')
                    @yield('footer_line')
                  @else
                    You're receiving this because of activity on your Grantly account.
                  @endif
                </p>
              </td>
            </tr>

          </table>

        </td>
      </tr>
    </table>

  </body>
</html>
