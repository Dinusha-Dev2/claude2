<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pixel Ceylon Tours — Sri Lanka Trip Planner
 * Email handler: wp_mail() (delivered by the site's SMTP plugin)
 */

// ── MAIL CONFIG ───────────────────────────────────────────────────────────────
defined( 'PCTP_FROM_NAME' ) || define( 'PCTP_FROM_NAME', 'Pixel Ceylon Tours' );
defined( 'PCTP_ADMIN_TO' ) || define( 'PCTP_ADMIN_TO', 'info@pixelceylontours.com' );

// ── AJAX HOOKS ────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_pctp_send_enquiry',        'pctp_handle_enquiry' );
add_action( 'wp_ajax_nopriv_pctp_send_enquiry', 'pctp_handle_enquiry' );

// ── MAILER ────────────────────────────────────────────────────────────────────
function pctp_smtp_send( $to, $subject, $html, $reply_to = '' ) {
    // Sent through wp_mail(), so the site's SMTP plugin (GoSMTP) handles delivery
    // and no mail credentials live in theme code.
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . PCTP_FROM_NAME . ' <' . PCTP_ADMIN_TO . '>',
    ];
    if ( $reply_to ) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }

    $error = '';
    $catch = function ( $wp_error ) use ( &$error ) {
        $error = $wp_error->get_error_message();
    };
    add_action( 'wp_mail_failed', $catch );
    $sent = wp_mail( $to, $subject, $html, $headers );
    remove_action( 'wp_mail_failed', $catch );

    if ( $sent ) {
        return true;
    }
    error_log( 'PCTP mail failed: ' . $error );
    return $error ?: 'Mail could not be sent.';
}

// ── HANDLER ───────────────────────────────────────────────────────────────────
function pctp_handle_enquiry() {
    if ( ! check_ajax_referer( 'pctp_enquiry_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
    }

    $fname      = sanitize_text_field( $_POST['fname']      ?? '' );
    $lname      = sanitize_text_field( $_POST['lname']      ?? '' );
    $email      = sanitize_email(      $_POST['email']      ?? '' );
    $phone      = sanitize_text_field( $_POST['phone']      ?? '' ) ?: 'Not provided';
    $month      = sanitize_text_field( $_POST['month']      ?? '' );
    $travellers = sanitize_text_field( $_POST['travellers'] ?? '' );
    $budget     = sanitize_text_field( $_POST['budget']     ?? '' ) ?: 'Not specified';
    $duration   = sanitize_text_field( $_POST['duration']   ?? '' ) ?: 'Not specified';
    $message    = sanitize_textarea_field( $_POST['message'] ?? '' );
    $total_days = intval( $_POST['total_days'] ?? 0 );
    $total_km   = intval( $_POST['total_km']   ?? 0 );
    $itin       = json_decode( stripslashes( $_POST['itinerary'] ?? '[]' ), true );
    if ( ! is_array( $itin ) ) $itin = [];

    if ( ! $fname || ! $lname || ! is_email( $email ) || ! $month || ! $travellers ) {
        wp_send_json_error( [ 'message' => 'Required fields missing.' ], 422 );
    }

    // Reference: YYYY-MMDD-HHmm  e.g. 2026-1203-1301
    $now    = current_time( 'timestamp' );
    $ref    = date( 'Y', $now ) . '-' . date( 'md', $now ) . '-' . date( 'Hi', $now );
    $d_long = date( 'j F Y', $now );
    $t_fmt  = date( 'H:i', $now );
    $name   = $fname . ' ' . $lname;

    // QR code via Google Charts — no library needed
    $qr_data = urlencode( 'Pixel Ceylon Tours | Ref: ' . $ref . ' | ' . $name . ' | ' . $email . ' | ' . $month . ' | ' . $travellers );
    $qr_url  = 'https://chart.googleapis.com/chart?cht=qr&chs=160x160&choe=UTF-8&chld=M|2&chl=' . $qr_data;

    $cats = [
        'unesco' => 'UNESCO Heritage', 'wildlife' => 'Wildlife & Safari',
        'beach'  => 'Beach & Coast',   'hills'    => 'Hill Country',
        'culture'=> 'Culture & Cities','experience'=> 'Local Experience',
    ];

    // Build itinerary table — clean white design
    $rows = '';
    foreach ( $itin as $i => $s ) {
        $n  = $i + 1;
        $sn = esc_html( $s['name']   ?? '' );
        $sr = esc_html( $s['region'] ?? '' );
        $sc = esc_html( $cats[ $s['cat'] ?? '' ] ?? '' );
        $sd = floatval( $s['days'] ?? 1 );
        $dl = $sd < 1 ? 'Half day' : ( ceil( $sd ) . ( $sd > 1 ? ' days' : ' day' ) );
        $bg = $i % 2 === 0 ? '#ffffff' : '#f8f9fb';
        $rows .= "
        <tr style='background:{$bg}'>
          <td style='padding:11px 14px;width:36px;text-align:center;border-bottom:1px solid #e5e7eb;
                     font-size:13px;font-weight:800;color:#f5a100'>{$n}</td>
          <td style='padding:11px 14px;border-bottom:1px solid #e5e7eb'>
            <div style='font-size:13px;font-weight:700;color:#071527'>{$sn}</div>
            <div style='font-size:10px;color:#9ca3af;margin-top:2px'>{$sr}</div>
          </td>
          <td style='padding:11px 14px;border-bottom:1px solid #e5e7eb;text-align:center'>
            <span style='font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;
                         color:#0fb3aa;background:rgba(15,179,170,.08);border:1px solid rgba(15,179,170,.2);
                         padding:3px 8px;border-radius:3px'>{$sc}</span>
          </td>
          <td style='padding:11px 14px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap'>
            <span style='font-size:10px;font-weight:700;color:#071527;background:#f0f2f5;
                         padding:3px 8px;border-radius:3px'>{$dl}</span>
          </td>
        </tr>";
    }
    $sc_count   = count( $itin );
    $itin_table = "
    <table width='100%' cellpadding='0' cellspacing='0' border='0'
           style='border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:24px'>
      <tr style='background:#071527'>
        <th style='padding:10px 14px;font-size:8px;font-weight:700;color:rgba(255,255,255,.5);
                   letter-spacing:.14em;text-transform:uppercase;text-align:left;
                   border-bottom:1px solid rgba(255,255,255,.06);width:36px'>#</th>
        <th style='padding:10px 14px;font-size:8px;font-weight:700;color:rgba(255,255,255,.5);
                   letter-spacing:.14em;text-transform:uppercase;text-align:left;
                   border-bottom:1px solid rgba(255,255,255,.06)'>Destination</th>
        <th style='padding:10px 14px;font-size:8px;font-weight:700;color:rgba(255,255,255,.5);
                   letter-spacing:.14em;text-transform:uppercase;text-align:center;
                   border-bottom:1px solid rgba(255,255,255,.06)'>Category</th>
        <th style='padding:10px 14px;font-size:8px;font-weight:700;color:rgba(255,255,255,.5);
                   letter-spacing:.14em;text-transform:uppercase;text-align:right;
                   border-bottom:1px solid rgba(255,255,255,.06)'>Recommended Stay</th>
      </tr>{$rows}
    </table>";

    $km_txt  = $total_km ? " &nbsp;&middot;&nbsp; ~{$total_km} km" : '';
    $msg_blk = $message  ? pctp_sec('Special Requests') . "
      <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:24px'>
        <tr>
          <td style='background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f5a100;
                     border-radius:0 6px 6px 0;padding:14px 18px;font-size:13px;
                     color:#374151;line-height:1.7'>
            " . nl2br( esc_html( $message ) ) . "
          </td>
        </tr>
      </table>" : '';

    $i_html = pctp_shell( pctp_admin_body( $name, $email, $phone, $month, $travellers, $budget, $duration, $ref, $d_long, $t_fmt, $sc_count, $total_days, $km_txt, $itin_table, $msg_blk, $qr_url ), $ref, $d_long );
    $c_html = pctp_shell( pctp_guest_body( $fname, $month, $travellers, $budget, $duration, $ref, $d_long, $t_fmt, $sc_count, $total_days, $km_txt, $itin_table, $msg_blk, $qr_url ), $ref, $d_long );

    $r1 = pctp_smtp_send( PCTP_ADMIN_TO, "[{$ref}] New Enquiry - {$name} | Pixel Ceylon Tours", $i_html, $email );
    $r2 = pctp_smtp_send( $email, "Your Sri Lanka Trip Enquiry - {$ref}", $c_html, PCTP_ADMIN_TO );

    if ( $r1 === true && $r2 === true ) {
        wp_send_json_success( [ 'message' => 'ok' ] );
    } elseif ( $r2 === true ) {
        error_log( 'PCTP admin mail failed [' . $ref . ']: ' . $r1 );
        wp_send_json_success( [ 'message' => 'ok' ] );
    } else {
        wp_send_json_error( [ 'message' => 'SMTP ERROR: ' . ( is_string( $r2 ) ? $r2 : 'Unknown' ) ], 500 );
    }
}

// ── EMAIL HELPERS ─────────────────────────────────────────────────────────────
// Design: Clean white card, navy #071527 header, orange #f5a100 accents, teal #0fb3aa highlights

/* ── Section label ─────────────────────────────────────────────── */
function pctp_sec( $label ) {
    return "
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin:0 0 14px 0'>
      <tr>
        <td style='padding-bottom:8px;border-bottom:1px solid #e5e7eb'>
          <span style='font-size:9px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#071527'>{$label}</span>
        </td>
      </tr>
    </table>";
}

/* ── Stat pill ──────────────────────────────────────────────────── */
function pctp_pill( $label, $value ) {
    return "<td style='padding:0 8px 0 0'>
      <table cellpadding='0' cellspacing='0' border='0'
             style='border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;min-width:96px'>
        <tr><td style='background:#f8f9fb;padding:7px 12px 4px'>
          <div style='font-size:8px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#0fb3aa'>{$label}</div>
        </td></tr>
        <tr><td style='background:#ffffff;padding:3px 12px 9px'>
          <div style='font-size:12px;font-weight:700;color:#071527;line-height:1.2'>{$value}</div>
        </td></tr>
      </table>
    </td>";
}

/* ── Outer shell ────────────────────────────────────────────────── */
function pctp_shell( $body, $ref, $date ) {
    $logo = 'https://pixelceylontours.com/wp-content/uploads/2026/03/5-scaled.png';
    $year = date( 'Y' );
    return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Pixel Ceylon Tours</title>
</head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased'>

<table width='100%' cellpadding='0' cellspacing='0' border='0' bgcolor='#f0f2f5'>
<tr><td align='center' style='padding:32px 16px 40px'>

<table width='580' cellpadding='0' cellspacing='0' border='0'
       style='max-width:580px;width:100%'>

  <!-- ── HEADER CARD ── -->
  <tr><td style='border-radius:10px 10px 0 0;overflow:hidden;padding:0'>

    <!-- Orange top bar -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0'>
      <tr><td style='background:#f5a100;height:5px;font-size:0;line-height:0'>&nbsp;</td></tr>
    </table>

    <!-- Nav bar: logo + ref badge -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0' bgcolor='#071527'>
      <tr>
        <td style='padding:18px 28px'>
          <img src='{$logo}' height='32' alt='Pixel Ceylon Tours'
               style='display:block;height:32px;width:auto'>
        </td>
        <td align='right' style='padding:18px 28px'>
          <table cellpadding='0' cellspacing='0' border='0' align='right'
                 style='border:1px solid rgba(15,179,170,.4);border-radius:5px;overflow:hidden'>
            <tr><td style='background:rgba(15,179,170,.12);padding:4px 12px 3px;
                           font-size:8px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;
                           color:#0fb3aa;text-align:center'>Reference</td></tr>
            <tr><td style='background:rgba(15,179,170,.06);padding:3px 12px 5px;
                           font-size:12px;font-weight:700;color:#f0ece2;letter-spacing:.04em;
                           text-align:center;font-family:Courier,monospace'>{$ref}</td></tr>
            <tr><td style='background:rgba(0,0,0,.15);padding:2px 12px 4px;
                           font-size:9px;color:rgba(240,236,226,.4);text-align:center'>{$date}</td></tr>
          </table>
        </td>
      </tr>
    </table>

    <!-- Teal divider -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0'>
      <tr><td style='background:#0fb3aa;height:2px;font-size:0;line-height:0'>&nbsp;</td></tr>
    </table>

  </td></tr>

  <!-- ── BODY ── -->
  <tr><td style='background:#ffffff;padding:32px 28px 28px'>{$body}</td></tr>

  <!-- ── FOOTER ── -->
  <tr><td style='border-radius:0 0 10px 10px;overflow:hidden;padding:0'>
    <table width='100%' cellpadding='0' cellspacing='0' border='0' bgcolor='#071527'>

      <!-- Orange top accent line -->
      <tr><td style='background:#f5a100;height:1px;font-size:0;line-height:0'>&nbsp;</td></tr>

      <!-- Brand row -->
      <tr>
        <td style='padding:16px 28px 12px'>
          <table width='100%' cellpadding='0' cellspacing='0' border='0'><tr>
            <td style='font-size:11px;color:rgba(240,236,226,.4);line-height:1.8'>
              <span style='color:#f5a100;font-weight:700'>Pixel Ceylon Tours</span>
              &nbsp;&middot;&nbsp; Sri Lanka Travel Specialists<br>
              <a href='https://pixelceylontours.com' style='color:#0fb3aa;text-decoration:none'>pixelceylontours.com</a>
              &nbsp;&middot;&nbsp;
              <a href='mailto:info@pixelceylontours.com' style='color:#0fb3aa;text-decoration:none'>info@pixelceylontours.com</a>
            </td>
            <td align='right' valign='middle' style='font-size:9px;color:rgba(255,255,255,.18);white-space:nowrap;padding-left:12px'>
              {$ref}
            </td>
          </tr></table>
        </td>
      </tr>

      <!-- Divider -->
      <tr><td style='padding:0 28px'><table width='100%' cellpadding='0' cellspacing='0' border='0'>
        <tr><td style='border-top:1px solid rgba(255,255,255,.06);font-size:0;line-height:0'>&nbsp;</td></tr>
      </table></td></tr>

      <!-- Developer credit row -->
      <tr>
        <td style='padding:10px 28px 16px'>
          <table width='100%' cellpadding='0' cellspacing='0' border='0'><tr>
            <td style='font-size:10px;color:rgba(240,236,226,.3);line-height:1.6'>
              System developed &amp; maintained by:&nbsp;
              <a href='https://www.facebook.com/profile.php?id=61567913782607&amp;mibextid=ZbWKwL'
                 target='_blank'
                 style='color:rgba(15,179,170,.65);text-decoration:none;font-weight:600'>
                Codewave Innovations
              </a>
            </td>
            <td align='right' style='font-size:10px;color:rgba(240,236,226,.25);white-space:nowrap;padding-left:12px'>
              &copy; All Rights Reserved {$year}
            </td>
          </tr></table>
        </td>
      </tr>

    </table>
  </td></tr>

</table>

</td></tr>
</table>

</body>
</html>";
}

/* ── Admin notification ─────────────────────────────────────────── */
function pctp_admin_body( $name, $email, $phone, $month, $travellers, $budget, $duration,
    $ref, $d_long, $t_fmt, $sc_count, $total_days, $km_txt, $itin_table, $msg_blk, $qr_url ) {

    $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=144x144&bgcolor=071527&color=0fb3aa&margin=6&format=png&data=' . rawurlencode( 'PCT|' . $ref . '|' . $name . '|' . $email );

    return "

    <!-- Alert bar -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:24px'>
      <tr>
        <td style='background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f5a100;
                   border-radius:0 6px 6px 0;padding:11px 16px'>
          <table width='100%' cellpadding='0' cellspacing='0' border='0'><tr>
            <td style='font-size:12px;font-weight:700;color:#92400e'>
              &#9679; New Trip Enquiry Received
            </td>
            <td align='right' style='font-size:11px;color:#b45309;white-space:nowrap'>{$d_long} &middot; {$t_fmt}</td>
          </tr></table>
        </td>
      </tr>
    </table>

    <!-- Contact + QR row -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:24px'>
      <tr>

        <!-- Contact table -->
        <td style='vertical-align:top;padding-right:20px'>
          " . pctp_sec('Guest Details') . "
          <table width='100%' cellpadding='0' cellspacing='0' border='0'
                 style='border:1px solid #e5e7eb;border-radius:8px;overflow:hidden'>
            <tr>
              <td style='background:#f8f9fb;padding:10px 14px;width:52px;border-bottom:1px solid #e5e7eb'>
                <span style='font-size:8px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af'>Name</span>
              </td>
              <td style='background:#ffffff;padding:10px 14px;border-bottom:1px solid #e5e7eb'>
                <span style='font-size:13px;font-weight:700;color:#071527'>{$name}</span>
              </td>
            </tr>
            <tr>
              <td style='background:#f8f9fb;padding:10px 14px;border-bottom:1px solid #e5e7eb'>
                <span style='font-size:8px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af'>Email</span>
              </td>
              <td style='background:#ffffff;padding:10px 14px;border-bottom:1px solid #e5e7eb'>
                <a href='mailto:{$email}' style='font-size:12px;color:#0fb3aa;font-weight:600;text-decoration:none'>{$email}</a>
              </td>
            </tr>
            <tr>
              <td style='background:#f8f9fb;padding:10px 14px'>
                <span style='font-size:8px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af'>Phone</span>
              </td>
              <td style='background:#ffffff;padding:10px 14px'>
                <span style='font-size:12px;color:#374151'>{$phone}</span>
              </td>
            </tr>
          </table>
        </td>

        <!-- QR block -->
        <td style='vertical-align:top;width:176px;text-align:center'>
          " . pctp_sec('Quick Scan') . "
          <table cellpadding='0' cellspacing='0' border='0' align='center'
                 style='border:1px solid #e5e7eb;border-radius:8px;overflow:hidden'>
            <tr>
              <td style='background:#071527;padding:12px;text-align:center'>
                <img src='{$qr}' width='144' height='144' alt='QR Code'
                     style='display:block;width:144px;height:144px;border-radius:4px'>
              </td>
            </tr>
            <tr>
              <td style='background:#0fb3aa;padding:7px 10px;text-align:center'>
                <span style='font-size:8px;font-weight:700;color:#071527;letter-spacing:.1em;text-transform:uppercase'>{$ref}</span>
              </td>
            </tr>
          </table>
        </td>

      </tr>
    </table>

    <!-- Preferences -->
    " . pctp_sec('Trip Preferences') . "
    <table cellpadding='0' cellspacing='0' border='0' style='margin-bottom:24px'>
      <tr>
        " . pctp_pill('Travel Month',  esc_html($month))      . "
        " . pctp_pill('Travellers',    esc_html($travellers)) . "
        " . pctp_pill('Budget',        esc_html($budget))     . "
        " . pctp_pill('Duration',      esc_html($duration))   . "
      </tr>
    </table>

    <!-- Itinerary -->
    " . pctp_sec("Planned Route &nbsp;&middot;&nbsp; {$sc_count} stop" . ($sc_count !== 1 ? 's' : '') . " &nbsp;&middot;&nbsp; {$total_days} days{$km_txt}") . "
    {$itin_table}
    {$msg_blk}

    <!-- CTA -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-top:4px'>
      <tr>
        <td align='center'>
          <a href='mailto:{$email}?subject=Re:+Sri+Lanka+Trip+Enquiry+{$ref}'
             style='display:inline-block;background:#071527;color:#ffffff;text-decoration:none;
                    font-size:13px;font-weight:700;padding:13px 36px;border-radius:6px;
                    letter-spacing:.03em;border:2px solid #071527'>
            Reply to {$name} &rarr;
          </a>
        </td>
      </tr>
    </table>";
}

/* ── Guest confirmation ─────────────────────────────────────────── */
function pctp_guest_body( $fname, $month, $travellers, $budget, $duration,
    $ref, $d_long, $t_fmt, $sc_count, $total_days, $km_txt, $itin_table, $msg_blk, $qr_url ) {

    $fn = esc_html( $fname );
    $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=144x144&bgcolor=071527&color=f5a100&margin=6&format=png&data=' . rawurlencode( 'PCT|' . $ref . '|' . $fn );

    return "

    <!-- Hero -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:28px'>
      <tr>
        <td style='background:#071527;border-radius:8px;padding:30px 28px;text-align:center'>

          <!-- Label -->
          <div style='font-size:9px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;
                      color:#f5a100;margin-bottom:12px'>Sri Lanka Trip Enquiry Confirmed</div>

          <!-- Headline -->
          <div style='font-size:28px;font-weight:700;color:#f0ece2;line-height:1.15;margin-bottom:10px'>
            Thank you, {$fn}!
          </div>

          <!-- Subline -->
          <div style='font-size:13px;color:rgba(240,236,226,.55);line-height:1.7;
                      max-width:360px;margin:0 auto'>
            Your enquiry is confirmed. We will send a personalised itinerary proposal within
            <span style='color:#f5a100;font-weight:700'>24 hours</span>.
          </div>

        </td>
      </tr>
    </table>

    <!-- Reference card + QR -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:28px'>
      <tr>

        <!-- Reference card -->
        <td style='vertical-align:middle;padding-right:20px'>
          <table width='100%' cellpadding='0' cellspacing='0' border='0'
                 style='border:1px solid #e5e7eb;border-radius:8px;overflow:hidden'>

            <!-- Orange top stripe -->
            <tr><td style='background:#f5a100;height:3px;font-size:0;line-height:0'>&nbsp;</td></tr>

            <!-- Label row -->
            <tr>
              <td style='background:#f8f9fb;padding:10px 16px 8px;
                         font-size:8px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
                         color:#071527;border-bottom:1px solid #e5e7eb'>
                Your Enquiry Reference
              </td>
            </tr>

            <!-- Ref number -->
            <tr>
              <td style='background:#ffffff;padding:14px 16px 6px;
                         font-size:20px;font-weight:800;color:#071527;
                         letter-spacing:.06em;font-family:Courier,monospace'>
                {$ref}
              </td>
            </tr>

            <!-- Date/time -->
            <tr>
              <td style='background:#ffffff;padding:4px 16px 14px;
                         font-size:11px;color:#9ca3af;border-bottom:1px solid #e5e7eb'>
                {$d_long} &middot; {$t_fmt}
              </td>
            </tr>

            <!-- Note -->
            <tr>
              <td style='background:#f8f9fb;padding:10px 16px;
                         font-size:11px;color:#6b7280;line-height:1.55'>
                Please quote this reference in all correspondence.
              </td>
            </tr>

          </table>
        </td>

        <!-- QR code -->
        <td style='vertical-align:middle;width:176px'>
          <table cellpadding='0' cellspacing='0' border='0' align='center'
                 style='border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;width:100%'>
            <tr><td style='background:#f5a100;height:3px;font-size:0;line-height:0'>&nbsp;</td></tr>
            <tr>
              <td style='background:#071527;padding:14px;text-align:center'>
                <img src='{$qr}' width='144' height='144' alt='Enquiry QR Code'
                     style='display:block;width:144px;height:144px;border-radius:4px'>
              </td>
            </tr>
            <tr>
              <td style='background:#f8f9fb;padding:8px 10px;text-align:center;
                         border-top:1px solid #e5e7eb;font-size:8px;font-weight:700;
                         color:#9ca3af;letter-spacing:.1em;text-transform:uppercase'>
                Scan to Verify
              </td>
            </tr>
          </table>
        </td>

      </tr>
    </table>

    <!-- Preferences -->
    " . pctp_sec('Your Booking Preferences') . "
    <table cellpadding='0' cellspacing='0' border='0' style='margin-bottom:24px'>
      <tr>
        " . pctp_pill('Travel Month',  esc_html($month))      . "
        " . pctp_pill('Travellers',    esc_html($travellers)) . "
        " . pctp_pill('Budget',        esc_html($budget))     . "
        " . pctp_pill('Duration',      esc_html($duration))   . "
      </tr>
    </table>

    <!-- Itinerary -->
    " . pctp_sec("Your Planned Itinerary &nbsp;&middot;&nbsp; {$sc_count} stop" . ($sc_count !== 1 ? 's' : '') . " &nbsp;&middot;&nbsp; {$total_days} days{$km_txt}") . "
    {$itin_table}
    {$msg_blk}

    <!-- What Happens Next -->
    " . pctp_sec('What Happens Next') . "
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom:28px'>
      <tr>
        <td style='vertical-align:top;width:38px;padding-bottom:6px'>
          <table cellpadding='0' cellspacing='0' border='0'>
            <tr><td style='background:#f5a100;width:28px;height:28px;border-radius:50%;
                           font-size:12px;font-weight:700;color:#071527;
                           text-align:center;line-height:28px'>1</td></tr>
          </table>
        </td>
        <td style='vertical-align:top;padding-left:8px;padding-bottom:18px;border-bottom:1px solid #f3f4f6'>
          <div style='font-size:13px;font-weight:700;color:#071527;margin-bottom:3px'>Review</div>
          <div style='font-size:12px;color:#6b7280;line-height:1.6'>Our specialists study your selected destinations and travel preferences carefully.</div>
        </td>
      </tr>
      <tr>
        <td style='vertical-align:top;width:38px;padding-top:16px;padding-bottom:6px'>
          <table cellpadding='0' cellspacing='0' border='0'>
            <tr><td style='background:#0fb3aa;width:28px;height:28px;border-radius:50%;
                           font-size:12px;font-weight:700;color:#ffffff;
                           text-align:center;line-height:28px'>2</td></tr>
          </table>
        </td>
        <td style='vertical-align:top;padding-left:8px;padding-top:16px;padding-bottom:18px;border-bottom:1px solid #f3f4f6'>
          <div style='font-size:13px;font-weight:700;color:#071527;margin-bottom:3px'>Proposal</div>
          <div style='font-size:12px;color:#6b7280;line-height:1.6'>A detailed, personalised itinerary with full pricing arrives in your inbox within 24 hours.</div>
        </td>
      </tr>
      <tr>
        <td style='vertical-align:top;width:38px;padding-top:16px'>
          <table cellpadding='0' cellspacing='0' border='0'>
            <tr><td style='background:#f5a100;width:28px;height:28px;border-radius:50%;
                           font-size:12px;font-weight:700;color:#071527;
                           text-align:center;line-height:28px'>3</td></tr>
          </table>
        </td>
        <td style='vertical-align:top;padding-left:8px;padding-top:16px'>
          <div style='font-size:13px;font-weight:700;color:#071527;margin-bottom:3px'>Confirm</div>
          <div style='font-size:12px;color:#6b7280;line-height:1.6'>Finalise your dream Sri Lanka journey — we handle everything from accommodation to transfers.</div>
        </td>
      </tr>
    </table>

    <!-- Contact strip -->
    <table width='100%' cellpadding='0' cellspacing='0' border='0'>
      <tr>
        <td style='background:#f8f9fb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px'>
          <table width='100%' cellpadding='0' cellspacing='0' border='0'><tr>
            <td>
              <div style='font-size:9px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
                          color:#0fb3aa;margin-bottom:5px'>Need help? Contact us</div>
              <a href='mailto:info@pixelceylontours.com'
                 style='font-size:13px;font-weight:700;color:#071527;text-decoration:none'>
                info@pixelceylontours.com
              </a>
            </td>
            <td align='right'>
              <a href='https://pixelceylontours.com'
                 style='font-size:11px;color:#9ca3af;text-decoration:none'>
                pixelceylontours.com
              </a>
            </td>
          </tr></table>
        </td>
      </tr>
    </table>";
}






/**
 * Pixel Ceylon Tours - Sri Lanka Trip Planner
 * Shortcode: [pixel_ceylon_trip_planner]
 */
add_shortcode('pixel_ceylon_trip_planner', function () {
    $uid = 'pctp_' . wp_generate_password(6, false, false);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($uid); ?>" class="pctp-wrap">

      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.cdnfonts.com/css/bagind" rel="stylesheet">
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
      <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

      <style>
        #<?php echo esc_html($uid); ?>{
          --navy:#071527;
          --navy2:#0b1e35;
          --navy3:#0e2440;
          --teal-d:#1a6b7a;
          --teal:#0fb3aa;
          --teal-l:#19d4cb;
          --orange:#f5a100;
          --orange-l:#ffb733;
          --cream:#f0ece2;
          --g1:rgba(255,255,255,.04);
          --g2:rgba(255,255,255,.07);
          --b1:rgba(255,255,255,.08);
          --b2:rgba(255,255,255,.05);
          --tx:#f0ece2;
          --tx2:rgba(240,236,226,.62);
          --tx3:rgba(240,236,226,.36);
          --teal-gl:rgba(15,179,170,.13);
          --teal-gl2:rgba(15,179,170,.07);
          --orange-gl:rgba(245,161,0,.15);
          --orange-gl2:rgba(245,161,0,.07);
          --cu:#f5a100;
          --cw:#1db87d;
          --cb:#0fb3aa;
          --ch:#3dc4a0;
          --cc:#7b68d4;
          --ce:#d46b4a;
          --serif:'Bagind','Playfair Display',Georgia,serif;
          --sans:'Poppins',system-ui,sans-serif;
          --mono:'JetBrains Mono',monospace;
          --ease:cubic-bezier(.4,0,.2,1);
          --spring:cubic-bezier(.34,1.56,.64,1);
          font-family:var(--sans);
        }

        #<?php echo esc_html($uid); ?>,
        #<?php echo esc_html($uid); ?> *,
        #<?php echo esc_html($uid); ?> *::before,
        #<?php echo esc_html($uid); ?> *::after{box-sizing:border-box}

        #<?php echo esc_html($uid); ?> img{display:block;max-width:100%}
        #<?php echo esc_html($uid); ?> button{font-family:inherit;cursor:pointer;border:none;background:none}
        #<?php echo esc_html($uid); ?> a{text-decoration:none;color:inherit}
        #<?php echo esc_html($uid); ?> .pctp-app{
          display:flex;height:100vh;max-height:100vh;overflow:hidden;position:relative;
          background:var(--navy);color:var(--tx);-webkit-font-smoothing:antialiased;
        }

        #<?php echo esc_html($uid); ?> .pctp-sidebar{
          width:380px;min-width:380px;height:100vh;background:var(--navy2);
          border-right:1px solid var(--b1);display:grid;
          grid-template-rows:auto auto auto 1fr auto auto;overflow:hidden;position:relative;z-index:100;
          box-shadow:4px 0 48px rgba(0,0,0,.55);
        }

        #<?php echo esc_html($uid); ?> .sb-head{
          padding:.85rem 1.15rem;
          border-bottom:1px solid var(--b1);
          background:linear-gradient(160deg, rgba(15,179,170,.05) 0%, rgba(245,161,0,.03) 50%, transparent 100%);
          display:flex;
          align-items:center;
          gap:.7rem;
          flex-wrap:nowrap;
        }

        #<?php echo esc_html($uid); ?> .sb-head-logo-link{
          display:inline-flex;
          align-items:center;
          justify-content:center;
          flex-shrink:0;
        }

        #<?php echo esc_html($uid); ?> .sb-head-logo-link:hover{opacity:.92}
        #<?php echo esc_html($uid); ?> .sb-head-logo{display:block;height:32px;width:auto;object-fit:contain;flex-shrink:0}

        #<?php echo esc_html($uid); ?> .sb-head-actions{
          display:flex;
          align-items:center;
          gap:.4rem;
          flex-shrink:0;
        }

        #<?php echo esc_html($uid); ?> .sb-head-btn{
          display:inline-flex;
          align-items:center;
          justify-content:center;
          gap:6px;
          height:30px;
          padding:0 .72rem;
          border-radius:999px;
          border:1px solid rgba(255,255,255,.1);
          background:rgba(255,255,255,.04);
          color:var(--tx2);
          font-size:.62rem;
          font-weight:500;
          line-height:1;
          transition:all .18s ease;
          white-space:nowrap;
        }

        #<?php echo esc_html($uid); ?> .sb-head-btn i{
          font-size:.58rem;
          color:var(--teal);
        }

        #<?php echo esc_html($uid); ?> .sb-head-btn:hover{
          background:rgba(255,255,255,.07);
          color:var(--tx);
          border-color:rgba(15,179,170,.22);
          transform:translateY(-1px);
        }

        #<?php echo esc_html($uid); ?> .sb-head-divider{
          width:1px;height:24px;background:linear-gradient(to bottom,transparent,rgba(255,255,255,.13),transparent);flex-shrink:0;
        }
        #<?php echo esc_html($uid); ?> .sb-head-right{display:flex;flex-direction:column;gap:4px;min-width:0}
        #<?php echo esc_html($uid); ?> .sb-head-tagline{
          font-size:.51rem;font-weight:600;color:var(--tx3);letter-spacing:.17em;text-transform:uppercase;white-space:nowrap
        }
        #<?php echo esc_html($uid); ?> .sb-head-dots{display:flex;align-items:center;gap:3px}
        #<?php echo esc_html($uid); ?> .sb-hd{width:4px;height:4px;border-radius:50%}
        #<?php echo esc_html($uid); ?> .sb-hd.t{background:var(--teal)}
        #<?php echo esc_html($uid); ?> .sb-hd.o{background:var(--orange)}
        #<?php echo esc_html($uid); ?> .sb-hd.d{background:rgba(255,255,255,.1)}

        #<?php echo esc_html($uid); ?> .sb-stats{
          display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--b2)
        }
        #<?php echo esc_html($uid); ?> .sbs{
          padding:.65rem .7rem;border-right:1px solid var(--b2);
          display:flex;flex-direction:column;align-items:center;gap:2px;transition:background .2s
        }
        #<?php echo esc_html($uid); ?> .sbs:last-child{border-right:none}
        #<?php echo esc_html($uid); ?> .sbs:hover{background:var(--g1)}
        #<?php echo esc_html($uid); ?> .sbs-val{
          font-family:var(--serif);font-size:1.4rem;font-weight:400;color:var(--teal-l);line-height:1
        }
        #<?php echo esc_html($uid); ?> .sbs-val.dim{color:var(--tx3);font-size:1.1rem}
        #<?php echo esc_html($uid); ?> .sbs-lbl{
          font-size:.55rem;font-weight:500;color:var(--tx3);letter-spacing:.07em;text-transform:uppercase;text-align:center;line-height:1.35
        }

        #<?php echo esc_html($uid); ?> .sb-itin-head{
          padding:.6rem 1.3rem .5rem;display:flex;align-items:center;justify-content:space-between;
          border-bottom:1px solid var(--b2)
        }
        #<?php echo esc_html($uid); ?> .sb-itin-title{
          display:flex;align-items:center;gap:7px;font-size:.6rem;font-weight:600;color:var(--tx3);letter-spacing:.14em;text-transform:uppercase
        }
        #<?php echo esc_html($uid); ?> .sb-itin-title i{color:var(--teal);font-size:.56rem}
        #<?php echo esc_html($uid); ?> .sb-actions{display:flex;gap:5px}
        #<?php echo esc_html($uid); ?> .sbtn{
          font-size:.6rem;font-weight:600;padding:4px 10px;border-radius:2px;border:1px solid var(--b1);
          color:var(--tx3);transition:all .18s;white-space:nowrap;letter-spacing:.02em
        }
        #<?php echo esc_html($uid); ?> .sbtn:hover{background:var(--g2);color:var(--tx);border-color:var(--b1)}
        #<?php echo esc_html($uid); ?> .sbtn.primary{background:var(--orange-gl);border-color:rgba(245,161,0,.3);color:var(--orange-l)}
        #<?php echo esc_html($uid); ?> .sbtn.primary:hover{background:rgba(245,161,0,.22)}
        #<?php echo esc_html($uid); ?> .sbtn.danger:hover{background:rgba(210,60,60,.12);border-color:rgba(210,60,60,.3);color:#e07070}

        #<?php echo esc_html($uid); ?> .sb-scroll{
          min-height:0;overflow-y:auto;padding:.5rem .75rem;
          scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.07) transparent;
        }
        #<?php echo esc_html($uid); ?> .sb-scroll::-webkit-scrollbar{width:3px}
        #<?php echo esc_html($uid); ?> .sb-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06);border-radius:2px}

        #<?php echo esc_html($uid); ?> .sb-empty{
          display:none;flex-direction:column;align-items:center;padding:1.5rem;text-align:center
        }
        #<?php echo esc_html($uid); ?> .sb-empty.vis{display:flex}
        #<?php echo esc_html($uid); ?> .sb-empty-ic{
          width:68px;height:68px;border-radius:2px;background:var(--orange-gl2);
          border:1px solid rgba(245,161,0,.14);display:flex;align-items:center;justify-content:center;
          font-size:1.6rem;color:rgba(245,161,0,.4);margin-bottom:1rem;
        }
        #<?php echo esc_html($uid); ?> .sb-empty-title{
          font-family:var(--serif);font-size:1.25rem;font-weight:400;color:var(--tx2);margin-bottom:.4rem
        }
        #<?php echo esc_html($uid); ?> .sb-empty-desc{
          font-size:.7rem;font-weight:300;color:var(--tx3);line-height:1.85;max-width:220px
        }
        #<?php echo esc_html($uid); ?> .sb-empty-tip{
          display:flex;align-items:center;gap:7px;margin-top:1.1rem;padding:.5rem .9rem;
          background:var(--g1);border:1px solid var(--b2);border-radius:2px;font-size:.62rem;color:var(--tx3)
        }
        #<?php echo esc_html($uid); ?> .sb-empty-tip i{color:var(--teal);font-size:.58rem}
        #<?php echo esc_html($uid); ?> .itin-list{display:flex;flex-direction:column;gap:3px}

        #<?php echo esc_html($uid); ?> .itin-conn{
          display:flex;align-items:center;gap:6px;padding:2px 1.2rem;
          font-family:var(--mono);font-size:.53rem;color:var(--tx3)
        }
        #<?php echo esc_html($uid); ?> .itin-conn-line{
          flex:1;height:1px;background:repeating-linear-gradient(90deg,var(--b2) 0,var(--b2) 4px,transparent 4px,transparent 8px)
        }

        #<?php echo esc_html($uid); ?> .itin-item{
          display:flex;align-items:center;gap:8px;padding:.55rem .7rem .55rem .45rem;border-radius:2px;
          border:1.5px solid transparent;background:var(--g1);transition:all .2s var(--ease);cursor:default;user-select:none
        }
        #<?php echo esc_html($uid); ?> .itin-item:hover{background:var(--g2);border-color:var(--b1)}
        #<?php echo esc_html($uid); ?> .itin-item.dragging{opacity:.4}
        #<?php echo esc_html($uid); ?> .itin-item.drag-over{border-color:var(--orange);background:var(--orange-gl2)}
        #<?php echo esc_html($uid); ?> .itin-handle{
          display:flex;flex-direction:column;gap:2px;cursor:grab;padding:3px 2px;flex-shrink:0;opacity:.28;transition:opacity .18s
        }
        #<?php echo esc_html($uid); ?> .itin-handle:hover{opacity:.65}
        #<?php echo esc_html($uid); ?> .itin-handle span{display:block;width:11px;height:1.5px;background:var(--tx2);border-radius:1px}
        #<?php echo esc_html($uid); ?> .itin-handle:active{cursor:grabbing}
        #<?php echo esc_html($uid); ?> .itin-num{
          width:26px;height:26px;flex-shrink:0;border-radius:2px;background:var(--orange-gl);
          border:1px solid rgba(245,161,0,.25);display:flex;align-items:center;justify-content:center;
          font-family:var(--serif);font-size:.95rem;font-weight:400;color:var(--orange-l)
        }
        #<?php echo esc_html($uid); ?> .itin-info{flex:1;min-width:0}
        #<?php echo esc_html($uid); ?> .itin-name{
          font-size:.74rem;font-weight:600;color:var(--tx);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.25;margin-bottom:2px
        }
        #<?php echo esc_html($uid); ?> .itin-meta{display:flex;align-items:center;gap:5px}
        #<?php echo esc_html($uid); ?> .itin-cat{
          font-size:.52rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:1px 6px;border-radius:2px;border:1px solid
        }
        #<?php echo esc_html($uid); ?> .itin-drive{
          font-family:var(--mono);font-size:.52rem;color:var(--tx3);display:flex;align-items:center;gap:3px
        }
        #<?php echo esc_html($uid); ?> .itin-days{
          font-family:var(--mono);font-size:.57rem;color:var(--tx3);background:var(--g1);padding:2px 6px;border-radius:2px;
          display:flex;align-items:center;gap:3px;flex-shrink:0
        }
        #<?php echo esc_html($uid); ?> .itin-rm{
          width:22px;height:22px;border-radius:2px;flex-shrink:0;color:var(--tx3);font-size:.6rem;
          display:flex;align-items:center;justify-content:center;transition:background .15s,color .15s
        }
        #<?php echo esc_html($uid); ?> .itin-rm:hover{background:rgba(200,55,55,.15);color:#e07070}

        #<?php echo esc_html($uid); ?> .sb-bottom{
          padding:.7rem 1rem;border-top:1px solid var(--b1);display:flex;flex-direction:column;gap:.4rem
        }
        #<?php echo esc_html($uid); ?> .sb-total{
          display:flex;align-items:center;justify-content:space-between;padding:.4rem .7rem;background:var(--g1);
          border:1px solid var(--b2);border-radius:2px;font-size:.65rem;color:var(--tx2)
        }
        #<?php echo esc_html($uid); ?> .sb-total strong{
          font-family:var(--serif);font-size:.9rem;font-weight:400;color:var(--teal-l)
        }
        #<?php echo esc_html($uid); ?> .sb-ctas{display:flex;gap:.45rem}
        #<?php echo esc_html($uid); ?> .sb-cta{
          flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:.55rem .5rem;border-radius:2px;
          font-size:.7rem;font-weight:600;border:1px solid;transition:all .22s;letter-spacing:.01em
        }
        #<?php echo esc_html($uid); ?> .sb-cta.teal{background:var(--teal-gl);border-color:rgba(15,179,170,.3);color:var(--teal-l)}
        #<?php echo esc_html($uid); ?> .sb-cta.teal:hover{background:rgba(15,179,170,.2);transform:translateY(-1px)}
        #<?php echo esc_html($uid); ?> .sb-cta.teal.active{background:rgba(15,179,170,.22);border-color:rgba(15,179,170,.55);color:var(--teal-l);box-shadow:0 0 0 2px rgba(15,179,170,.18)}
        #<?php echo esc_html($uid); ?> .sb-cta.orange{background:var(--orange-gl);border-color:rgba(245,161,0,.35);color:var(--orange-l)}
        #<?php echo esc_html($uid); ?> .sb-cta.orange:hover{background:rgba(245,161,0,.22);transform:translateY(-1px)}

        /* ── Codewave credit strip — sidebar bottom ── */
        #<?php echo esc_html($uid); ?> .pctp-credit{
          display:flex;align-items:center;justify-content:center;gap:5px;
          padding:.45rem .7rem .3rem;
          border-top:1px solid var(--b2);
          font-size:.52rem;color:var(--tx3);letter-spacing:.03em;text-align:center;line-height:1.5
        }
        #<?php echo esc_html($uid); ?> .pctp-credit a{
          color:var(--teal);text-decoration:none;font-weight:600;transition:color .18s
        }
        #<?php echo esc_html($uid); ?> .pctp-credit a:hover{color:var(--teal-l)}
        #<?php echo esc_html($uid); ?> .pctp-credit i{font-size:.48rem;color:var(--orange);opacity:.7}

        /* ── Map watermark badge — bottom-right ── */
        #<?php echo esc_html($uid); ?> .map-dev-badge{
          position:absolute;bottom:2.8rem;right:.85rem;z-index:500;
          display:flex;align-items:center;gap:5px;
          background:rgba(7,21,39,.78);backdrop-filter:blur(12px);
          border:1px solid rgba(255,255,255,.06);border-radius:2px;
          padding:.3rem .65rem;pointer-events:auto
        }
        #<?php echo esc_html($uid); ?> .map-dev-badge span{
          font-size:.48rem;color:var(--tx3);letter-spacing:.04em
        }
        #<?php echo esc_html($uid); ?> .map-dev-badge a{
          font-size:.48rem;font-weight:600;color:rgba(15,179,170,.65);
          text-decoration:none;transition:color .18s
        }
        #<?php echo esc_html($uid); ?> .map-dev-badge a:hover{color:var(--teal-l)}

        /* ── Modal developer note ── */
        #<?php echo esc_html($uid); ?> .modal-dev-note{
          text-align:center;padding:.5rem 1.7rem .2rem;
          font-size:.55rem;color:var(--tx3);letter-spacing:.02em;flex-shrink:0
        }
        #<?php echo esc_html($uid); ?> .modal-dev-note a{
          color:var(--teal);text-decoration:none;font-weight:600
        }
        #<?php echo esc_html($uid); ?> .modal-dev-note a:hover{color:var(--teal-l)}

        /* ── Success screen developer note ── */
        #<?php echo esc_html($uid); ?> .success-dev{
          margin-top:.5rem;display:flex;align-items:center;gap:6px;
          padding:.5rem .9rem;background:var(--g1);border:1px solid var(--b2);
          border-radius:2px;font-size:.56rem;color:var(--tx3);
        }
        #<?php echo esc_html($uid); ?> .success-dev i{color:var(--orange);font-size:.5rem}
        #<?php echo esc_html($uid); ?> .success-dev a{
          color:var(--teal);text-decoration:none;font-weight:600
        }
        #<?php echo esc_html($uid); ?> .success-dev a:hover{color:var(--teal-l)}

        #<?php echo esc_html($uid); ?> .pctp-map-area{flex:1;position:relative;overflow:hidden}
        #<?php echo esc_html($uid); ?> .pctp-main-map{width:100%;height:100%}

        #<?php echo esc_html($uid); ?> .pctp-filter-bar{
          position:absolute;top:.85rem;left:50%;transform:translateX(-50%);z-index:500;
          display:flex;align-items:center;gap:3px;background:rgba(7,21,39,.9);backdrop-filter:blur(20px) saturate(160%);
          border:1px solid var(--b1);border-radius:2px;padding:4px;box-shadow:0 8px 40px rgba(0,0,0,.55)
        }
        #<?php echo esc_html($uid); ?> .fb-btn{
          display:flex;align-items:center;gap:6px;padding:.36rem .85rem;border-radius:2px;font-size:.65rem;font-weight:500;
          color:var(--tx3);cursor:pointer;transition:all .2s var(--ease);border:1.5px solid transparent;white-space:nowrap
        }
        #<?php echo esc_html($uid); ?> .fb-btn i{font-size:.57rem}
        #<?php echo esc_html($uid); ?> .fb-btn .ct{font-family:var(--mono);font-size:.48rem;opacity:.5}
        #<?php echo esc_html($uid); ?> .fb-btn:hover{color:var(--tx);background:var(--g2)}
        #<?php echo esc_html($uid); ?> .fb-btn.on{background:var(--g2);border-color:var(--fbc,var(--teal));color:var(--tx)}

        #<?php echo esc_html($uid); ?> .map-badge{
          position:absolute;z-index:500;background:rgba(7,21,39,.88);backdrop-filter:blur(18px);border:1px solid var(--b1);border-radius:2px;pointer-events:none
        }
        #<?php echo esc_html($uid); ?> .count-badge{
          bottom:1rem;left:1rem;padding:.5rem .9rem;display:flex;align-items:center;gap:8px
        }
        #<?php echo esc_html($uid); ?> .cb-dot{
          width:7px;height:7px;border-radius:50%;background:var(--teal-l);animation:pctpDotPulse 2.5s ease-in-out infinite;flex-shrink:0
        }
        @keyframes pctpDotPulse{
          0%,100%{box-shadow:0 0 0 0 rgba(25,212,203,.55)}
          50%{box-shadow:0 0 0 7px rgba(25,212,203,0)}
        }
        #<?php echo esc_html($uid); ?> .cb-num{font-family:var(--serif);font-size:1.05rem;font-weight:400;color:var(--tx)}
        #<?php echo esc_html($uid); ?> .cb-lbl{font-size:.57rem;color:var(--tx3)}

        #<?php echo esc_html($uid); ?> .hint-badge{
          bottom:1rem;left:50%;transform:translateX(-50%);padding:.4rem .8rem;font-size:.59rem;color:var(--tx3);
          display:flex;align-items:center;gap:6px
        }
        #<?php echo esc_html($uid); ?> .hint-badge i{color:var(--teal);font-size:.56rem}

        /* View Route badge — shown when route-only mode is active */
        #<?php echo esc_html($uid); ?> .route-mode-badge{
          position:absolute;top:3.8rem;left:50%;transform:translateX(-50%);z-index:501;
          display:none;align-items:center;gap:7px;padding:.35rem .9rem;
          background:rgba(15,179,170,.15);backdrop-filter:blur(14px);
          border:1px solid rgba(15,179,170,.4);border-radius:2px;
          font-size:.6rem;font-weight:600;color:var(--teal-l);white-space:nowrap;pointer-events:none
        }
        #<?php echo esc_html($uid); ?> .route-mode-badge.show{display:flex}
        #<?php echo esc_html($uid); ?> .route-mode-badge i{font-size:.55rem}

        #<?php echo esc_html($uid); ?> .btn-fit-map{
          position:absolute;top:.85rem;right:.85rem;z-index:500;display:none;align-items:center;gap:7px;padding:.48rem 1rem;
          background:rgba(7,21,39,.9);backdrop-filter:blur(18px);border:1px solid rgba(245,161,0,.35);border-radius:2px;
          color:var(--orange-l);font-size:.67rem;font-weight:600;font-family:var(--sans);cursor:pointer;transition:all .2s;
          box-shadow:0 4px 20px rgba(0,0,0,.4)
        }
        #<?php echo esc_html($uid); ?> .btn-fit-map:hover{background:var(--orange-gl)}
        #<?php echo esc_html($uid); ?> .btn-fit-map.show{display:flex}

        #<?php echo esc_html($uid); ?> .detail-panel{
          position:absolute;top:4rem;right:.85rem;z-index:600;width:320px;background:rgba(7,21,39,.97);
          backdrop-filter:blur(28px) saturate(180%);border:1px solid rgba(255,255,255,.1);border-radius:2px;overflow:hidden;
          box-shadow:0 30px 80px rgba(0,0,0,.75);display:none;flex-direction:column;opacity:0;transform:translateX(16px) scale(.97);
          transition:opacity .28s var(--ease),transform .34s var(--spring)
        }
        #<?php echo esc_html($uid); ?> .detail-panel.vis{display:flex;opacity:1;transform:none}

        #<?php echo esc_html($uid); ?> .dp-close{
          position:absolute;top:.7rem;right:.75rem;z-index:10;width:32px;height:32px;min-width:32px;min-height:32px;padding:0;
          border-radius:999px;aspect-ratio:1 / 1;background:rgba(7,21,39,.82);backdrop-filter:blur(10px);
          -webkit-backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.12);color:var(--tx3);font-size:.68rem;line-height:1;
          display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .18s ease;box-sizing:border-box;
        }
        #<?php echo esc_html($uid); ?> .dp-close:hover{background:rgba(255,255,255,.1);color:var(--tx)}
        #<?php echo esc_html($uid); ?> .dp-img{position:relative;height:180px;flex-shrink:0;overflow:hidden}
        #<?php echo esc_html($uid); ?> .dp-img img{
          width:100%;height:100%;object-fit:cover;filter:brightness(.68) saturate(1.1);transition:transform .55s var(--ease)
        }
        #<?php echo esc_html($uid); ?> .detail-panel:hover .dp-img img{transform:scale(1.05)}
        #<?php echo esc_html($uid); ?> .dp-img::after{
          content:'';position:absolute;inset:0;background:linear-gradient(to bottom,transparent 45%,rgba(7,21,39,.5) 100%)
        }
        #<?php echo esc_html($uid); ?> .dp-catbadge{
          position:absolute;top:.75rem;left:.85rem;z-index:2;display:flex;align-items:center;gap:5px;background:rgba(7,21,39,.78);
          backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.1);border-radius:2px;padding:3px 10px 3px 7px;
          font-size:.53rem;font-weight:700;letter-spacing:.09em;text-transform:uppercase
        }
        #<?php echo esc_html($uid); ?> .dp-catdot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
        #<?php echo esc_html($uid); ?> .dp-region{
          position:absolute;bottom:.75rem;right:.8rem;z-index:2;background:rgba(7,21,39,.7);backdrop-filter:blur(8px);
          border:1px solid rgba(255,255,255,.08);border-radius:2px;padding:3px 9px;font-size:.57rem;color:var(--tx2);display:flex;align-items:center;gap:4px
        }
        #<?php echo esc_html($uid); ?> .dp-region i{color:var(--orange);font-size:.52rem}
        #<?php echo esc_html($uid); ?> .dp-body{
          padding:1rem 1.1rem .95rem;display:flex;flex-direction:column;gap:.65rem;overflow-y:auto;max-height:430px
        }
        #<?php echo esc_html($uid); ?> .dp-body::-webkit-scrollbar{width:2px}
        #<?php echo esc_html($uid); ?> .dp-body::-webkit-scrollbar-thumb{background:var(--b1);border-radius:1px}
        #<?php echo esc_html($uid); ?> .dp-name{
          font-family:var(--serif);font-size:1.5rem;font-weight:400;color:var(--tx);line-height:1.1
        }
        #<?php echo esc_html($uid); ?> .dp-tagline{
          font-size:.7rem;font-weight:300;color:var(--tx2);line-height:1.75;border-left:2px solid rgba(245,161,0,.4);
          padding:.48rem .72rem;background:var(--orange-gl2);border-radius:0 2px 2px 0
        }
        #<?php echo esc_html($uid); ?> .dp-facts{display:flex;flex-wrap:wrap;gap:.28rem}
        #<?php echo esc_html($uid); ?> .dp-fact{
          display:flex;align-items:center;gap:5px;background:var(--g1);border:1px solid var(--b2);border-radius:2px;padding:4px 8px;
          font-size:.61rem;color:var(--tx2)
        }
        #<?php echo esc_html($uid); ?> .dp-fact i{font-size:.55rem;color:var(--teal)}
        #<?php echo esc_html($uid); ?> .dp-fact strong{color:var(--tx);font-weight:600}
        #<?php echo esc_html($uid); ?> .dp-best{
          display:flex;align-items:flex-start;gap:8px;background:var(--orange-gl2);border:1px solid rgba(245,161,0,.15);border-radius:2px;padding:.55rem .8rem
        }
        #<?php echo esc_html($uid); ?> .dp-best i{color:var(--orange);font-size:.72rem;flex-shrink:0;margin-top:2px}
        #<?php echo esc_html($uid); ?> .dp-best-txt{font-size:.67rem;font-weight:300;color:var(--tx2);line-height:1.55}
        #<?php echo esc_html($uid); ?> .dp-best-txt strong{color:var(--orange-l);display:block;font-size:.67rem;font-weight:600;margin-bottom:1px}
        #<?php echo esc_html($uid); ?> .dp-chips{display:flex;flex-wrap:wrap;gap:.24rem}
        #<?php echo esc_html($uid); ?> .dp-chip{background:var(--g1);border:1px solid var(--b2);border-radius:2px;padding:3px 9px;font-size:.58rem;color:var(--tx3)}
        #<?php echo esc_html($uid); ?> .dp-cta{padding-top:.2rem}
        #<?php echo esc_html($uid); ?> .dp-addbtn{
          width:100%;display:flex;align-items:center;justify-content:center;gap:7px;padding:.68rem;border-radius:2px;font-size:.74rem;
          font-weight:600;cursor:pointer;transition:all .2s;border:1.5px solid;letter-spacing:.02em
        }
        #<?php echo esc_html($uid); ?> .dp-addbtn.add{background:var(--orange-gl);border-color:rgba(245,161,0,.4);color:var(--orange-l)}
        #<?php echo esc_html($uid); ?> .dp-addbtn.add:hover{background:rgba(245,161,0,.22);transform:translateY(-1px)}
        #<?php echo esc_html($uid); ?> .dp-addbtn.remove{background:rgba(210,55,55,.08);border-color:rgba(210,55,55,.3);color:#e07070}
        #<?php echo esc_html($uid); ?> .dp-addbtn.remove:hover{background:rgba(210,55,55,.15)}

        #<?php echo esc_html($uid); ?> .tooltip{
          position:fixed;z-index:9999;pointer-events:none;display:none;transform:translate(-50%,-100%) translateY(-13px);
          filter:drop-shadow(0 16px 48px rgba(0,0,0,.65))
        }
        #<?php echo esc_html($uid); ?> .tt-wrap{
          width:215px;background:rgba(7,21,39,.97);backdrop-filter:blur(20px);border:1px solid var(--b1);border-radius:2px;overflow:hidden
        }
        #<?php echo esc_html($uid); ?> .tt-img{width:100%;height:108px;overflow:hidden}
        #<?php echo esc_html($uid); ?> .tt-img img{width:100%;height:100%;object-fit:cover;filter:brightness(.7) saturate(1.1)}
        #<?php echo esc_html($uid); ?> .tt-body{padding:.6rem .85rem .75rem}
        #<?php echo esc_html($uid); ?> .tt-cat{display:flex;align-items:center;gap:5px;margin-bottom:.28rem}
        #<?php echo esc_html($uid); ?> .tt-cd{width:6px;height:6px;border-radius:50%;flex-shrink:0}
        #<?php echo esc_html($uid); ?> .tt-cl{font-size:.52rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
        #<?php echo esc_html($uid); ?> .tt-name{font-family:var(--serif);font-size:1rem;font-weight:400;color:var(--tx);line-height:1.2;margin-bottom:.2rem}
        #<?php echo esc_html($uid); ?> .tt-hint{font-size:.63rem;font-weight:300;color:var(--tx2);line-height:1.48}
        #<?php echo esc_html($uid); ?> .tt-days{margin-top:.3rem;font-family:var(--mono);font-size:.53rem;color:var(--tx3);display:flex;align-items:center;gap:4px}
        #<?php echo esc_html($uid); ?> .tt-arrow{width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-top:8px solid rgba(7,21,39,.97);margin:0 auto}

        #<?php echo esc_html($uid); ?> .slm{position:relative;display:flex;align-items:center;justify-content:center;cursor:pointer}
        #<?php echo esc_html($uid); ?> .slm-ring{
          position:absolute;inset:0;border-radius:50%;border:2px solid var(--mc,#0fb3aa);animation:pctpRingPulse 2.8s ease-out infinite;pointer-events:none
        }
        @keyframes pctpRingPulse{
          0%{transform:scale(.82);opacity:.6}
          70%,100%{transform:scale(2.3);opacity:0}
        }
        #<?php echo esc_html($uid); ?> .slm-body{
          width:100%;height:100%;border-radius:50%;background:var(--mc,#0fb3aa);border:2.5px solid rgba(255,255,255,.92);
          display:flex;align-items:center;justify-content:center;color:#fff;position:relative;z-index:1;box-shadow:0 4px 16px rgba(0,0,0,.45);
          transition:transform .22s var(--spring);font-family:var(--sans)
        }
        #<?php echo esc_html($uid); ?> .slm-body:hover{transform:scale(1.2)}
        #<?php echo esc_html($uid); ?> .slm-body i{font-size:.72rem;pointer-events:none}
        #<?php echo esc_html($uid); ?> .slm-body.num{font-weight:800;font-size:.72rem}
        #<?php echo esc_html($uid); ?> .slm.in-itin .slm-body{
          border-color:#fff;box-shadow:0 0 0 3px var(--mc,#0fb3aa),0 4px 20px rgba(0,0,0,.5)
        }

        #<?php echo esc_html($uid); ?> .drive-lbl{
          background:rgba(7,21,39,.88);backdrop-filter:blur(10px);border:1px solid var(--b1);border-radius:2px;
          padding:2px 8px;font-family:var(--mono);font-size:.53rem;color:var(--tx3);white-space:nowrap
        }

        #<?php echo esc_html($uid); ?> .leaflet-control-zoom{border:none !important;box-shadow:none !important;margin:.85rem !important}
        #<?php echo esc_html($uid); ?> .leaflet-control-zoom a{
          background:rgba(7,21,39,.92) !important;backdrop-filter:blur(10px);
          border:1px solid var(--b1) !important;color:var(--tx3) !important;width:32px !important;height:32px !important;
          line-height:30px !important;font-size:.8rem !important;transition:all .2s !important
        }
        #<?php echo esc_html($uid); ?> .leaflet-control-zoom a:hover{
          background:var(--teal-gl) !important;color:var(--teal-l) !important;border-color:rgba(15,179,170,.3) !important
        }
        #<?php echo esc_html($uid); ?> .leaflet-control-zoom-in{border-radius:2px 2px 0 0 !important}
        #<?php echo esc_html($uid); ?> .leaflet-control-zoom-out{border-radius:0 0 2px 2px !important}
        #<?php echo esc_html($uid); ?> .leaflet-control-attribution{
          background:rgba(7,21,39,.78) !important;color:rgba(240,236,226,.22) !important;font-size:.46rem !important;border-radius:2px 0 0 0 !important;backdrop-filter:blur(8px)
        }
        #<?php echo esc_html($uid); ?> .leaflet-control-attribution a{color:rgba(15,179,170,.4) !important}

        #<?php echo esc_html($uid); ?> .modal-overlay{
          position:fixed;inset:0;z-index:9000;background:rgba(7,21,39,.78);backdrop-filter:blur(8px) saturate(120%);
          display:none;align-items:center;justify-content:center;padding:1rem
        }
        #<?php echo esc_html($uid); ?> .modal-overlay.open{display:flex}
        #<?php echo esc_html($uid); ?> .enquiry-modal{
          width:100%;max-width:580px;height:92vh;max-height:92vh;background:var(--navy2);
          border:1px solid var(--b1);border-radius:2px;overflow:hidden;display:flex;flex-direction:column;
          box-shadow:0 40px 100px rgba(0,0,0,.75),0 0 0 1px rgba(15,179,170,.07);animation:pctpModalIn .32s var(--spring) both
        }
        #<?php echo esc_html($uid); ?> .modal-main{
          display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden
        }
        @keyframes pctpModalIn{
          from{opacity:0;transform:translateY(28px) scale(.97)}
          to{opacity:1;transform:none}
        }
        #<?php echo esc_html($uid); ?> .modal-head{
          padding:1.4rem 1.7rem 1.1rem;background:linear-gradient(135deg,rgba(15,179,170,.07) 0%,transparent 60%);
          border-bottom:1px solid var(--b2);flex-shrink:0;position:relative
        }

        #<?php echo esc_html($uid); ?> .modal-close{
          position:absolute;top:1rem;right:1rem;width:32px;height:32px;min-width:32px;min-height:32px;padding:0;
          border-radius:999px;aspect-ratio:1 / 1;background:var(--g1);border:1px solid var(--b1);color:var(--tx3);font-size:.68rem;
          line-height:1;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .18s ease;box-sizing:border-box;
        }
        #<?php echo esc_html($uid); ?> .modal-close:hover{background:var(--g2);color:var(--tx)}
        #<?php echo esc_html($uid); ?> .modal-eyebrow{display:flex;align-items:center;gap:8px;margin-bottom:.6rem}
        #<?php echo esc_html($uid); ?> .modal-eyeline{width:24px;height:1.5px;background:var(--teal);border-radius:1px}
        #<?php echo esc_html($uid); ?> .modal-eyetxt{font-size:.58rem;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--teal)}
        #<?php echo esc_html($uid); ?> .modal-title{
          font-family:var(--serif);font-size:1.75rem;font-weight:400;color:var(--tx);line-height:1.1;margin-bottom:.4rem
        }
        #<?php echo esc_html($uid); ?> .modal-title span{color:var(--teal-l);font-style:italic}
        #<?php echo esc_html($uid); ?> .modal-sub{font-size:.7rem;font-weight:300;color:var(--tx3);line-height:1.65}

        #<?php echo esc_html($uid); ?> .modal-summary{
          padding:.75rem 1.7rem;border-bottom:1px solid var(--b2);flex-shrink:0;background:var(--g1);max-height:160px;overflow-y:auto
        }
        #<?php echo esc_html($uid); ?> .modal-summary::-webkit-scrollbar{width:2px}
        #<?php echo esc_html($uid); ?> .modal-summary::-webkit-scrollbar-thumb{background:var(--b1);border-radius:1px}
        #<?php echo esc_html($uid); ?> .modal-sum-title{
          font-size:.57rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--tx3);margin-bottom:.5rem
        }
        #<?php echo esc_html($uid); ?> .modal-stops{display:flex;flex-wrap:wrap;gap:.25rem}

        /* Modal stop item — now includes a remove button */
        #<?php echo esc_html($uid); ?> .modal-stop{
          display:flex;align-items:center;gap:5px;background:var(--g2);border:1px solid var(--b2);border-radius:2px;
          padding:3px 6px 3px 9px;font-size:.63rem;color:var(--tx2);transition:border-color .18s,background .18s
        }
        #<?php echo esc_html($uid); ?> .modal-stop:hover{border-color:var(--b1);background:rgba(255,255,255,.06)}
        #<?php echo esc_html($uid); ?> .modal-stop-num{
          width:16px;height:16px;border-radius:2px;background:var(--orange-gl);border:1px solid rgba(245,161,0,.3);
          display:flex;align-items:center;justify-content:center;font-family:var(--serif);font-size:.75rem;font-weight:400;color:var(--orange-l);flex-shrink:0
        }
        #<?php echo esc_html($uid); ?> .modal-stop-rm{
          width:18px;height:18px;flex-shrink:0;border-radius:2px;color:var(--tx3);font-size:.52rem;
          display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s,color .15s;
          border:none;background:none;padding:0;margin-left:2px
        }
        #<?php echo esc_html($uid); ?> .modal-stop-rm:hover{background:rgba(200,55,55,.18);color:#e07070}

        #<?php echo esc_html($uid); ?> .modal-sum-meta{display:flex;gap:1rem;margin-top:.55rem;font-family:var(--mono);font-size:.58rem;color:var(--tx3)}
        #<?php echo esc_html($uid); ?> .modal-sum-meta i{color:var(--teal);margin-right:4px}

        #<?php echo esc_html($uid); ?> .modal-form{
          padding:1.1rem 1.7rem;overflow-y:auto;flex:1;min-height:0;display:flex;flex-direction:column;gap:.8rem
        }
        #<?php echo esc_html($uid); ?> .modal-form::-webkit-scrollbar{width:3px}
        #<?php echo esc_html($uid); ?> .modal-form::-webkit-scrollbar-thumb{background:var(--b1);border-radius:2px}
        #<?php echo esc_html($uid); ?> .form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
        #<?php echo esc_html($uid); ?> .form-group{display:flex;flex-direction:column;gap:.32rem}
        #<?php echo esc_html($uid); ?> .form-group.full{grid-column:1 / -1}
        #<?php echo esc_html($uid); ?> .form-label{
          font-size:.62rem;font-weight:600;color:var(--tx3);letter-spacing:.05em
        }
        #<?php echo esc_html($uid); ?> .form-label span{color:rgba(245,161,0,.7);margin-left:2px}
        #<?php echo esc_html($uid); ?> .form-input,
        #<?php echo esc_html($uid); ?> .form-select,
        #<?php echo esc_html($uid); ?> .form-textarea{
          background:var(--g1);border:1.5px solid var(--b1);border-radius:2px;padding:.62rem .85rem;color:var(--tx);
          font-family:var(--sans);font-size:.75rem;font-weight:400;transition:border-color .2s,background .2s;width:100%;outline:none;-webkit-appearance:none
        }
        #<?php echo esc_html($uid); ?> .form-input::placeholder{color:var(--tx3)}
        #<?php echo esc_html($uid); ?> .form-input:focus,
        #<?php echo esc_html($uid); ?> .form-select:focus,
        #<?php echo esc_html($uid); ?> .form-textarea:focus{
          border-color:var(--teal);background:rgba(15,179,170,.05)
        }
        #<?php echo esc_html($uid); ?> .form-select{
          background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='rgba(240,236,226,.3)' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
          background-repeat:no-repeat;background-position:right .85rem center;background-size:10px;cursor:pointer
        }
        #<?php echo esc_html($uid); ?> .form-select option{background:var(--navy2);color:var(--tx)}
        #<?php echo esc_html($uid); ?> .form-textarea{resize:vertical;min-height:80px;line-height:1.6}

        #<?php echo esc_html($uid); ?> .modal-submit-row{
          display:flex;align-items:center;gap:.65rem;padding:.9rem 1.7rem 1.2rem;border-top:1px solid var(--b2);flex-shrink:0;background:var(--navy2)
        }
        #<?php echo esc_html($uid); ?> .modal-submit{
          flex:1;display:flex;align-items:center;justify-content:center;gap:9px;padding:.82rem;background:var(--teal);
          border:none;border-radius:2px;color:var(--navy);font-size:.8rem;font-weight:700;font-family:var(--sans);cursor:pointer;transition:all .22s;letter-spacing:.03em
        }
        #<?php echo esc_html($uid); ?> .modal-submit:hover{background:var(--teal-l);transform:translateY(-1px);box-shadow:0 8px 28px rgba(15,179,170,.3)}
        #<?php echo esc_html($uid); ?> .modal-submit:disabled{opacity:.5;cursor:not-allowed;transform:none}
        #<?php echo esc_html($uid); ?> .modal-privacy{
          font-size:.6rem;color:var(--tx3);line-height:1.55;display:flex;align-items:flex-start;gap:6px;padding-top:.1rem
        }
        #<?php echo esc_html($uid); ?> .modal-privacy i{color:var(--teal);font-size:.58rem;flex-shrink:0;margin-top:2px}

        #<?php echo esc_html($uid); ?> .modal-success{
          display:none;flex-direction:column;align-items:center;justify-content:center;padding:3rem 2rem;text-align:center;gap:1.1rem;flex:1;min-height:0
        }
        #<?php echo esc_html($uid); ?> .modal-success.show{display:flex}
        #<?php echo esc_html($uid); ?> .success-icon{
          width:80px;height:80px;border-radius:50%;background:var(--teal-gl);border:2px solid rgba(15,179,170,.3);
          display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--teal-l);animation:pctpSuccessPop .5s var(--spring) both
        }
        @keyframes pctpSuccessPop{
          from{transform:scale(0);opacity:0}
          to{transform:scale(1);opacity:1}
        }
        #<?php echo esc_html($uid); ?> .success-title{font-family:var(--serif);font-size:1.9rem;font-weight:400;color:var(--tx);line-height:1.1}
        #<?php echo esc_html($uid); ?> .success-title em{font-style:italic;color:var(--teal-l)}
        #<?php echo esc_html($uid); ?> .success-desc{font-size:.74rem;font-weight:300;color:var(--tx2);line-height:1.8;max-width:340px}
        #<?php echo esc_html($uid); ?> .success-back{
          padding:.65rem 2rem;border-radius:2px;background:var(--teal-gl);border:1px solid rgba(15,179,170,.3);color:var(--teal-l);
          font-size:.72rem;font-weight:600;cursor:pointer;transition:all .2s
        }
        #<?php echo esc_html($uid); ?> .success-back:hover{background:rgba(15,179,170,.2)}

        #<?php echo esc_html($uid); ?> .mob-overlay{
          display:none;position:fixed;inset:0;z-index:4999;background:rgba(7,21,39,.55);backdrop-filter:blur(3px)
        }
        #<?php echo esc_html($uid); ?> .mob-overlay.show{display:block}
        #<?php echo esc_html($uid); ?> .mob-toggle{
          display:none;padding:.85rem 1.5rem;background:var(--navy2);
          border-bottom:1px solid var(--b1);align-items:center;justify-content:space-between;cursor:pointer;flex-shrink:0;width:100%
        }
        #<?php echo esc_html($uid); ?> .mob-toggle .mt-label{
          font-size:.74rem;font-weight:600;color:var(--orange-l);display:flex;align-items:center;gap:7px
        }
        #<?php echo esc_html($uid); ?> .mob-toggle .mt-count{font-family:var(--mono);font-size:.67rem;color:var(--tx3)}

        @media (max-width:900px){
          #<?php echo esc_html($uid); ?> .pctp-sidebar{
            width:100%;min-width:100%;position:fixed;bottom:0;left:0;z-index:5000;height:75vh;display:grid;
            grid-template-rows:auto auto auto auto 1fr auto auto;border-right:none;border-top:1px solid var(--b1);
            transform:translateY(calc(100% - 52px));transition:transform .4s var(--ease);box-shadow:0 -12px 50px rgba(0,0,0,.6)
          }
          #<?php echo esc_html($uid); ?> .pctp-sidebar.open{transform:translateY(0)}
          #<?php echo esc_html($uid); ?> .pctp-map-area{height:100vh}
          #<?php echo esc_html($uid); ?> .mob-toggle{display:flex}
          #<?php echo esc_html($uid); ?> .detail-panel{width:calc(100% - 1.5rem);max-width:360px}
          #<?php echo esc_html($uid); ?> .form-row{grid-template-columns:1fr}
          #<?php echo esc_html($uid); ?> .btn-fit-map{top:3.8rem;right:.7rem}
          #<?php echo esc_html($uid); ?> .pctp-filter-bar{left:.7rem;right:.7rem;transform:none;width:auto;max-width:calc(100% - 1.4rem)}
          #<?php echo esc_html($uid); ?> .route-mode-badge{top:7.5rem}
        }

        @media (max-width:620px){
          #<?php echo esc_html($uid); ?> .sb-head{gap:.5rem;padding:.8rem .9rem;}
          #<?php echo esc_html($uid); ?> .sb-head-btn{width:30px;min-width:30px;padding:0;border-radius:999px;}
          #<?php echo esc_html($uid); ?> .sb-head-btn span{display:none}
          #<?php echo esc_html($uid); ?> .sb-head-divider{display:none}
          #<?php echo esc_html($uid); ?> .sb-head-right{min-width:0}
          #<?php echo esc_html($uid); ?> .pctp-filter-bar{max-width:calc(100vw - 1.4rem);overflow-x:auto}
          #<?php echo esc_html($uid); ?> .pctp-filter-bar::-webkit-scrollbar{display:none}
          #<?php echo esc_html($uid); ?> .fb-btn .ct{display:none}
          #<?php echo esc_html($uid); ?> .enquiry-modal{height:96vh;max-height:96vh}
          #<?php echo esc_html($uid); ?> .modal-head{padding:1.2rem 1.3rem 1rem}
          #<?php echo esc_html($uid); ?> .modal-form{padding:1rem 1.3rem}
          #<?php echo esc_html($uid); ?> .modal-submit-row{padding:.75rem 1.3rem 1rem}
          #<?php echo esc_html($uid); ?> .modal-summary{padding:.7rem 1.3rem}
        }
      </style>

      <div class="pctp-app">

        <aside class="pctp-sidebar">
          <div class="mob-toggle">
            <span class="mt-label"><i class="fas fa-route"></i>My Itinerary</span>
            <span class="mt-count">0 stops</span>
          </div>
          <div class="sb-head">
            <a class="sb-head-logo-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Go to homepage">
              <img class="sb-head-logo" src="https://pixelceylontours.com/wp-content/uploads/2026/03/5-scaled.png" alt="Pixel Ceylon Tours">
            </a>

            <div class="sb-head-actions">
              <a class="sb-head-btn" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Home">
                <i class="fas fa-house"></i>
                <span>Home</span>
              </a>

              <a class="sb-head-btn" href="mailto:codewaveinnovation@gmail.com" aria-label="Email us">
                <i class="fas fa-envelope"></i>
                <span>Email</span>
              </a>
            </div>

            <div class="sb-head-divider"></div>

            <div class="sb-head-right">
              <div class="sb-head-tagline">Trip Planner</div>
              <div class="sb-head-dots">
                <span class="sb-hd t"></span>
                <span class="sb-hd o"></span>
                <span class="sb-hd d"></span>
                <span class="sb-hd d"></span>
                <span class="sb-hd d"></span>
              </div>
            </div>
          </div>

          <div class="sb-stats">
            <div class="sbs"><div class="sbs-val stat-stops">0</div><div class="sbs-lbl">Stops<br>Added</div></div>
            <div class="sbs"><div class="sbs-val dim stat-days">--</div><div class="sbs-lbl">Est.<br>Days</div></div>
            <div class="sbs"><div class="sbs-val dim stat-km">--</div><div class="sbs-lbl">Est.<br>km</div></div>
          </div>

          <div class="sb-itin-head">
            <div class="sb-itin-title"><i class="fas fa-map-marked-alt"></i>My Itinerary</div>
            <div class="sb-actions">
              <button class="sbtn primary btn-optimize"><i class="fas fa-magic"></i> Optimize</button>
              <button class="sbtn danger btn-clear"><i class="fas fa-trash-alt"></i> Clear</button>
            </div>
          </div>

          <div class="sb-scroll">
            <div class="sb-empty vis">
              <div class="sb-empty-ic"><i class="fas fa-map-marked-alt"></i></div>
              <div class="sb-empty-title">Your journey starts here</div>
              <div class="sb-empty-desc">Click any glowing pin on the map to explore destinations and build your personalised Sri Lanka route.</div>
              <div class="sb-empty-tip"><i class="fas fa-hand-pointer"></i>Click pins on the map to begin</div>
            </div>
            <div class="itin-list"></div>
          </div>

          <div class="sb-bottom">
            <div class="sb-total" style="display:none">
              <span>Estimated route distance</span>
              <strong class="total-label">--</strong>
            </div>
            <div class="sb-ctas">
              <!-- "View Route" replaces "Fit Route" — toggles route-only marker visibility -->
              <button class="sb-cta teal btn-view-route"><i class="fas fa-route"></i> View Route</button>
              <button class="sb-cta orange btn-enquire"><i class="fas fa-paper-plane"></i> Enquire Now</button>
            </div>
          </div>

          <!-- Codewave credit strip -->
          <div class="pctp-credit">
            <i class="fas fa-code"></i>
            Developed &amp; maintained by&nbsp;
            <a href="https://www.facebook.com/profile.php?id=61567913782607&mibextid=ZbWKwL" target="_blank" rel="noopener">Codewave Innovations</a>
            &nbsp;&middot;&nbsp; &copy; <?php echo date('Y'); ?>
          </div>
        </aside>

        <main class="pctp-map-area">
          <div class="pctp-main-map"></div>

          <div class="pctp-filter-bar">
            <button class="fb-btn on" data-cat="all" style="--fbc:var(--teal)"><i class="fas fa-globe-asia"></i>All <span class="ct">35</span></button>
            <button class="fb-btn" data-cat="unesco" style="--fbc:var(--cu)"><i class="fas fa-award"></i>UNESCO <span class="ct">7</span></button>
            <button class="fb-btn" data-cat="wildlife" style="--fbc:var(--cw)"><i class="fas fa-paw"></i>Wildlife <span class="ct">6</span></button>
            <button class="fb-btn" data-cat="beach" style="--fbc:var(--cb)"><i class="fas fa-umbrella-beach"></i>Beaches <span class="ct">8</span></button>
            <button class="fb-btn" data-cat="hills" style="--fbc:var(--ch)"><i class="fas fa-mountain"></i>Hills <span class="ct">6</span></button>
            <button class="fb-btn" data-cat="culture" style="--fbc:var(--cc)"><i class="fas fa-city"></i>Culture <span class="ct">5</span></button>
            <button class="fb-btn" data-cat="experience" style="--fbc:var(--ce)"><i class="fas fa-star"></i>Experiences <span class="ct">3</span></button>
          </div>

          <div class="map-badge count-badge">
            <div class="cb-dot"></div>
            <span class="cb-num">35</span>
            <span class="cb-lbl">&ensp;destinations</span>
          </div>

          <div class="map-badge hint-badge">
            <i class="fas fa-hand-pointer"></i>Click a pin to explore &amp; add to route
          </div>

          <!-- Developer watermark badge -->
          <div class="map-dev-badge">
            <span>Built by</span>
            <a href="https://www.facebook.com/profile.php?id=61567913782607&mibextid=ZbWKwL" target="_blank" rel="noopener">Codewave Innovations</a>
          </div>

          <!-- Route-only mode indicator badge -->
          <div class="route-mode-badge">
            <i class="fas fa-eye"></i>Showing route only &mdash; click <strong style="margin:0 3px">View Route</strong> again to show all
          </div>

          <button class="btn-fit-map"><i class="fas fa-compress-alt"></i>&ensp;Fit Route</button>
          <div class="detail-panel"></div>
        </main>
      </div>

      <div class="mob-overlay"></div>

      <div class="tooltip">
        <div class="tt-wrap">
          <div class="tt-img"><img class="tt-img-el" src="" alt=""></div>
          <div class="tt-body">
            <div class="tt-cat"><div class="tt-cd"></div><span class="tt-cl"></span></div>
            <div class="tt-name"></div>
            <div class="tt-hint"></div>
            <div class="tt-days"></div>
          </div>
        </div>
        <div class="tt-arrow"></div>
      </div>

      <div class="modal-overlay" role="dialog" aria-modal="true">
        <div class="enquiry-modal">
          <div class="modal-main">
            <div class="modal-head">
              <button class="modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
              <div class="modal-eyebrow">
                <div class="modal-eyeline"></div>
                <span class="modal-eyetxt">Trip Enquiry</span>
              </div>
              <div class="modal-title">Plan Your <span>Sri Lanka</span> Journey</div>
              <div class="modal-sub">Our travel specialists will craft a tailored itinerary based on your selected destinations and preferences.</div>
            </div>

            <div class="modal-summary">
              <div class="modal-sum-title">Your Selected Itinerary</div>
              <div class="modal-stops"></div>
              <div class="modal-sum-meta"></div>
            </div>

            <form class="modal-form enquiry-form" novalidate>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">First Name <span>*</span></label>
                  <input class="form-input" type="text" name="fname" placeholder="John" required autocomplete="given-name">
                </div>
                <div class="form-group">
                  <label class="form-label">Last Name <span>*</span></label>
                  <input class="form-input" type="text" name="lname" placeholder="Smith" required autocomplete="family-name">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Email Address <span>*</span></label>
                  <input class="form-input" type="email" name="email" placeholder="john@example.com" required autocomplete="email">
                </div>
                <div class="form-group">
                  <label class="form-label">Phone / WhatsApp</label>
                  <input class="form-input" type="tel" name="phone" placeholder="+1 234 567 8900" autocomplete="tel">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Travel Month <span>*</span></label>
                  <select class="form-select form-input" name="month" required>
                    <option value="" disabled selected>Select month</option>
                    <option>January</option><option>February</option><option>March</option>
                    <option>April</option><option>May</option><option>June</option>
                    <option>July</option><option>August</option><option>September</option>
                    <option>October</option><option>November</option><option>December</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">No. of Travellers <span>*</span></label>
                  <select class="form-select form-input" name="travellers" required>
                    <option value="" disabled selected>Select</option>
                    <option>Solo traveller</option><option>2 travellers</option>
                    <option>3-4 travellers</option><option>5-8 travellers</option>
                    <option>9+ travellers</option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Budget Range</label>
                  <select class="form-select form-input" name="budget">
                    <option value="" disabled selected>Select budget</option>
                    <option>Budget (Under $80/night)</option>
                    <option>Mid-range ($80 - $150/night)</option>
                    <option>Luxury ($150 - $300/night)</option>
                    <option>Ultra-luxury ($300+/night)</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Trip Duration</label>
                  <select class="form-select form-input" name="duration">
                    <option value="" disabled selected>Select</option>
                    <option>3-5 days</option><option>6-8 days</option>
                    <option>9-12 days</option><option>13-16 days</option>
                    <option>17+ days</option>
                  </select>
                </div>
              </div>
              <div class="form-group full">
                <label class="form-label">Special Requests or Questions</label>
                <textarea class="form-textarea form-input" name="message" placeholder="Any dietary requirements, accessibility needs, special occasions, or questions about your itinerary..."></textarea>
              </div>
            </form>

            <div class="modal-submit-row">
              <button class="modal-submit submit-btn">
                <i class="fas fa-paper-plane"></i>&ensp;Send Enquiry
              </button>
              <div class="modal-privacy">
                <i class="fas fa-lock"></i>
                Your details are private and will only be used to plan your trip.
              </div>
            </div>

            <!-- Developer note -->
            <div class="modal-dev-note">
              System developed &amp; maintained by <a href="https://www.facebook.com/profile.php?id=61567913782607&mibextid=ZbWKwL" target="_blank" rel="noopener">Codewave Innovations</a> &nbsp;&middot;&nbsp; &copy; <?php echo date('Y'); ?> All Rights Reserved
            </div>
          </div>

          <div class="modal-success">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <div class="success-title">Enquiry <em>Sent!</em></div>
            <div class="success-desc">Thank you! A Pixel Ceylon Tours specialist will contact you within 24 hours with a personalised itinerary proposal.</div>
            <button class="success-back"><i class="fas fa-arrow-left" style="margin-right:6px"></i>Back to Planner</button>
            
          </div>
        </div>
      </div>

      <script>
      (function(){
        // WordPress AJAX endpoint + nonce (rendered server-side, safe to inline)
        const PCTP_AJAX_URL   = '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>';
        const PCTP_AJAX_NONCE = '<?php echo esc_js( wp_create_nonce("pctp_enquiry_nonce") ); ?>';

        const root = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!root || typeof L === 'undefined') return;

        const els = {
          sidebar: root.querySelector('.pctp-sidebar'),
          mapEl: root.querySelector('.pctp-main-map'),
          filterBtns: root.querySelectorAll('.fb-btn'),
          countNum: root.querySelector('.cb-num'),
          detailPanel: root.querySelector('.detail-panel'),
          tooltip: root.querySelector('.tooltip'),
          ttImg: root.querySelector('.tt-img-el'),
          ttCd: root.querySelector('.tt-cd'),
          ttCl: root.querySelector('.tt-cl'),
          ttName: root.querySelector('.tt-name'),
          ttHint: root.querySelector('.tt-hint'),
          ttDays: root.querySelector('.tt-days'),
          itinList: root.querySelector('.itin-list'),
          emptyState: root.querySelector('.sb-empty'),
          statStops: root.querySelector('.stat-stops'),
          statDays: root.querySelector('.stat-days'),
          statKm: root.querySelector('.stat-km'),
          totalWrap: root.querySelector('.sb-total'),
          totalLabel: root.querySelector('.total-label'),
          btnViewRoute: root.querySelector('.btn-view-route'),
          btnFitMap: root.querySelector('.btn-fit-map'),
          btnOptimize: root.querySelector('.btn-optimize'),
          btnClear: root.querySelector('.btn-clear'),
          btnEnquire: root.querySelector('.btn-enquire'),
          mobToggle: root.querySelector('.mob-toggle'),
          mobOverlay: root.querySelector('.mob-overlay'),
          mobCount: root.querySelector('.mt-count'),
          routeModeBadge: root.querySelector('.route-mode-badge'),
          modalOverlay: root.querySelector('.modal-overlay'),
          modalMain: root.querySelector('.modal-main'),
          modalSuccess: root.querySelector('.modal-success'),
          modalClose: root.querySelector('.modal-close'),
          enquiryForm: root.querySelector('.enquiry-form'),
          submitBtn: root.querySelector('.submit-btn'),
          modalStops: root.querySelector('.modal-stops'),
          modalMeta: root.querySelector('.modal-sum-meta'),
          successBack: root.querySelector('.success-back')
        };

        const CAT = {
          unesco:     { label:'UNESCO Heritage',   color:'#f5a100', icon:'fa-award' },
          wildlife:   { label:'Wildlife & Safari', color:'#1db87d', icon:'fa-paw' },
          beach:      { label:'Beach & Coast',     color:'#0fb3aa', icon:'fa-umbrella-beach' },
          hills:      { label:'Hill Country',      color:'#3dc4a0', icon:'fa-mountain' },
          culture:    { label:'Culture & Cities',  color:'#7b68d4', icon:'fa-city' },
          experience: { label:'Local Experience',  color:'#d46b4a', icon:'fa-star' }
        };

        const SPOTS = [
          { id:'sigiriya', cat:'unesco', name:'Sigiriya Rock Fortress', lat:7.9570, lng:80.7603, region:'Cultural Triangle', days:1, bestTime:'Nov - Apr', hint:'200 m volcanic citadel - ancient frescoes - panoramic views', tagline:"5th-century royal citadel rising 200 m from the jungle -- extraordinary frescoes, lion-paw gateway and breathtaking summit panoramas over the rainforest.", facts:[{i:'fa-mountain',l:'Height',v:'200 m'},{i:'fa-calendar',l:'Era',v:'5th c. AD'},{i:'fa-award',l:'Status',v:'UNESCO WHS'}], chips:['Lion Paw Gate','Frescoes','Water Gardens','Summit Sunrise'], img:'https://pixelceylontours.com/wp-content/uploads/2026/02/sigirija1.webp' },
          { id:'anuradhapura', cat:'unesco', name:'Anuradhapura', lat:8.3468, lng:80.3960, region:'North Central Province', days:2, bestTime:'Year-round', hint:"Sacred ancient capital - 2,250-yr-old Bodhi Tree - vast stupas", tagline:"One of the world's oldest cities -- founded 3rd century BC, home to the 2,250-year-old Sri Maha Bodhi tree and colossal ancient stupas.", facts:[{i:'fa-tree',l:'Bodhi Tree',v:'2,250 yrs'},{i:'fa-calendar',l:'Founded',v:'3rd c. BC'},{i:'fa-bicycle',l:'Best By',v:'Cycling'}], chips:['Sri Maha Bodhi','Ruwanwelisaya','Abhayagiri','Cycling Tour'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Anuradhapura_processed.webp' },
          { id:'polonnaruwa', cat:'unesco', name:'Polonnaruwa', lat:7.9395, lng:81.0027, region:'North Central Province', days:1, bestTime:'Nov - Apr', hint:'Medieval royal capital - Gal Vihara rock sculptures', tagline:"Medieval capital with intact royal palaces, serene Gal Vihara rock sculptures and the great Parakrama Samudra reservoir.", facts:[{i:'fa-crown',l:'Capital Era',v:'11th-13th c.'},{i:'fa-image',l:'Icon',v:'Gal Vihara'},{i:'fa-award',l:'Status',v:'UNESCO WHS'}], chips:['Royal Palace','Gal Vihara','Vatadage','Parakrama Samudra'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/polonnaruwa_processed.webp' },
          { id:'dambulla', cat:'unesco', name:'Dambulla Cave Temple', lat:7.8647, lng:80.6497, region:'Central Province', days:0.5, bestTime:'Nov - Apr', hint:"80 gilded Buddhas - 2,100 m2 murals - Asia's finest cave temple", tagline:"Five sacred caves sheltering 80 gilded Buddha statues and 2,100 m2 of extraordinary ceiling paintings -- Asia's most magnificent cave temple.", facts:[{i:'fa-place-of-worship',l:'Caves',v:'5'},{i:'fa-spa',l:'Statues',v:'80 Buddhas'},{i:'fa-award',l:'Status',v:'UNESCO WHS'}], chips:['5 Cave Chambers','Ceiling Paintings','Rock Summit','Golden Temple'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/dambulla-cave-temple_processed.webp' },
          { id:'kandy-temple', cat:'unesco', name:'Temple of the Sacred Tooth', lat:7.2942, lng:80.6418, region:'Kandy - Hill Capital', days:1, bestTime:'Year-round', hint:'Sacred Tooth Relic - 3 puja ceremonies daily - UNESCO WHS', tagline:"Sri Lanka's most sacred Buddhist shrine -- three daily candlelit puja ceremonies with drums and incense fill the gilded halls with devotion.", facts:[{i:'fa-pray',l:'Puja',v:'3x daily'},{i:'fa-mountain',l:'Altitude',v:'500 m'},{i:'fa-award',l:'Status',v:'UNESCO WHS'}], chips:['Tooth Relic Puja','Kandy Lake','Cultural Dance','Royal Museum'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/temple-of-tooth_processed.webp' },
          { id:'galle-fort', cat:'unesco', name:'Galle Dutch Fort', lat:6.0270, lng:80.2168, region:'Southern Province', days:1, bestTime:'Nov - Apr', hint:'Best-preserved European fort in Asia - Dutch ramparts - ocean sunsets', tagline:"Best-preserved European colonial fort in Asia -- cobblestone lanes, Dutch ramparts over the Indian Ocean, boutique galleries and ocean-view cafes.", facts:[{i:'fa-fort-awesome',l:'Built',v:'1663 Dutch'},{i:'fa-water',l:'Setting',v:'Oceanfront'},{i:'fa-award',l:'Status',v:'UNESCO WHS'}], chips:['Fort Ramparts','Lighthouse','Colonial Streets','Boutique Shops'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Galle-Dutch-Fort_processed.webp' },
          { id:'sinharaja', cat:'unesco', name:'Sinharaja Forest Reserve', lat:6.4000, lng:80.5000, region:'Sabaragamuwa Province', days:1, bestTime:'Jan - Apr', hint:"Last intact tropical rainforest - 60% endemic species", tagline:"Sri Lanka's last pristine primary rainforest -- a UNESCO Biosphere Reserve with 60% endemic trees, rare birds and extraordinary biodiversity.", facts:[{i:'fa-tree',l:'Area',v:'11,187 ha'},{i:'fa-dove',l:'Birds',v:'147 species'},{i:'fa-award',l:'Status',v:'UNESCO WHS'}], chips:['Endemic Birds','Guided Trek','Forest Waterfalls','Purple-faced Langur'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Sinharaja-Forest_processed.webp' },
          { id:'yala', cat:'wildlife', name:'Yala National Park', lat:6.3724, lng:81.5167, region:'Southern Province', days:2, bestTime:'Feb - Jul', hint:"World's highest leopard density - dawn jeep safaris", tagline:"The world's highest leopard density per km2 -- plus wild elephants, sloth bears, mugger crocodiles and over 215 bird species.", facts:[{i:'fa-paw',l:'Famous For',v:'Leopards'},{i:'fa-map',l:'Area',v:'979 km2'},{i:'fa-dove',l:'Birds',v:'215+'}], chips:['Leopard Safaris','Wild Elephants','Sloth Bears','Coastal Lagoons'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Yala-National-Park_processed.webp' },
          { id:'udawalawe', cat:'wildlife', name:'Udawalawe National Park', lat:6.4386, lng:80.8997, region:'Sabaragamuwa / Uva Province', days:1, bestTime:'Year-round', hint:'700+ wild elephants guaranteed - open grassland jeep safaris', tagline:"Best park for guaranteed elephant sightings -- herds of 200+ elephants roam open grasslands around a glittering reservoir.", facts:[{i:'fa-map',l:'Area',v:'308 km2'},{i:'fa-paw',l:'Elephants',v:'700+'},{i:'fa-sun',l:'Best Drive',v:'6 AM / 3 PM'}], chips:['Elephant Herds','Water Buffalo','Crocodiles','Reservoir Views'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Udawalawe-National-Park_processed.webp' },
          { id:'minneriya', cat:'wildlife', name:'Minneriya National Park', lat:8.0350, lng:80.8997, region:'North Central Province', days:0.5, bestTime:'Jun - Oct', hint:"'The Gathering' -- 300+ elephants converge in dry season", tagline:"Home to 'The Gathering' -- one of Earth's greatest wildlife spectacles, where 300+ wild elephants converge on the ancient Minneriya tank.", facts:[{i:'fa-map',l:'Area',v:'88.9 km2'},{i:'fa-calendar',l:'Peak',v:'Jun - Oct'},{i:'fa-paw',l:'Elephants',v:'300+'}], chips:["The Gathering",'Sambar Deer','Painted Stork','Ancient Tank'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/minneriya-national-park_processed.webp' },
          { id:'wilpattu', cat:'wildlife', name:'Wilpattu National Park', lat:8.4667, lng:80.0333, region:'North Western Province', days:1, bestTime:'Feb - Oct', hint:"Sri Lanka's largest park - villu lakes - far fewer tourists", tagline:"Sri Lanka's largest national park -- a hauntingly beautiful wilderness of natural lakes with secretive leopards, sloth bears and fewer crowds.", facts:[{i:'fa-map',l:'Area',v:'1,317 km2'},{i:'fa-water',l:'Feature',v:'Villu Lakes'},{i:'fa-paw',l:'Animals',v:'Leopard & Bear'}], chips:['Leopards','Sloth Bears','Flamingos','Villu Lakes'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/leaport-tour.webp' },
          { id:'pinnawala', cat:'wildlife', name:'Pinnawala Elephant Orphanage', lat:7.2760, lng:80.0700, region:'Sabaragamuwa - Near Colombo', days:0.5, bestTime:'Year-round', hint:'Rescued elephants - river bathing - baby elephant photography', tagline:"The world's most heartwarming elephant sanctuary -- watch rescued elephants being fed and bathing in the Maha Oya river.", facts:[{i:'fa-paw',l:'Residents',v:'90+ elephants'},{i:'fa-water',l:'River Bath',v:'10 AM & 2 PM'},{i:'fa-camera',l:'Photos',v:'Up close'}], chips:['River Bathing','Baby Elephants','Feeding Session','Photography'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/pinnawala_processed.webp' },
          { id:'kumana', cat:'wildlife', name:'Kumana National Park', lat:6.5800, lng:81.6833, region:'Eastern Province', days:1, bestTime:'Apr - Jul', hint:'Remote wetland park - thousands of nesting birds - rarely visited', tagline:"A remote, unspoilt park famous for spectacular bird migrations -- thousands of painted storks, herons and pelicans nest around the Kumana lagoon.", facts:[{i:'fa-dove',l:'Birds',v:'255 species'},{i:'fa-map',l:'Area',v:'36,096 ha'},{i:'fa-water',l:'Feature',v:'Kumana Lagoon'}], chips:['Bird Migration','Painted Stork','Wild Buffaloes','Mangroves'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Kumana-National-Park_processed.webp' },
          { id:'mirissa', cat:'beach', name:'Mirissa Beach', lat:5.9483, lng:80.4716, region:'Southern Province', days:2, bestTime:'Nov - Apr', hint:'Blue whale capital of the world - crescent paradise beach', tagline:"A golden crescent bay fringed by coconut palms -- the world's premier blue whale watching destination and a legendary surf and sunset spot.", facts:[{i:'fa-fish',l:'Famous For',v:'Blue Whales'},{i:'fa-umbrella-beach',l:'Beach Vibe',v:'Relaxed'},{i:'fa-water',l:'Surf',v:'Nov - Apr'}], chips:['Blue Whale Watching','Parrot Rock','Coconut Tree Hill','Sunset Point'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Mirissa-Beach_processed.webp' },
          { id:'unawatuna', cat:'beach', name:'Unawatuna Beach', lat:6.0106, lng:80.2494, region:'Southern Province - Near Galle', days:1, bestTime:'Nov - Apr', hint:'Sheltered coral bay - top snorkelling - fresh seafood', tagline:"A sheltered horseshoe bay with clear waters perfect for snorkelling coral reefs, lazy beach days and candlelit seafood dinners.", facts:[{i:'fa-water',l:'Swimming',v:'Calm all year'},{i:'fa-fish',l:'Snorkelling',v:'Coral Reef'},{i:'fa-utensils',l:'Food',v:'Excellent'}], chips:['Snorkelling','Jungle Beach','Peace Pagoda','Sunset Dining'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Unawatuna-Beach_processed.webp' },
          { id:'trincomalee', cat:'beach', name:'Trincomalee & Nilaveli', lat:8.5874, lng:81.2152, region:'Eastern Province', days:2, bestTime:'May - Sep', hint:'East coast paradise - Pigeon Island reef - spinner dolphins', tagline:"Sri Lanka's east coast gem -- pristine white sands, spectacular Pigeon Island snorkelling, spinner dolphins and a magnificent natural harbour.", facts:[{i:'fa-umbrella-beach',l:'Beach',v:'Nilaveli'},{i:'fa-fish',l:'Snorkelling',v:'Pigeon Island'},{i:'fa-water',l:'Wildlife',v:'Spinner Dolphins'}], chips:['Pigeon Island','Snorkelling','Whale Watching','Fort Frederick'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Trincomalee_processed.webp' },
          { id:'arugambay', cat:'beach', name:'Arugam Bay', lat:6.8399, lng:81.8354, region:'Eastern Province', days:2, bestTime:'May - Oct', hint:"Asia's top surf destination - legendary right-hander waves", tagline:"Asia's iconic right-hand surf point -- a laid-back lagoon village with world-class waves, abundant wildlife and an unforgettable bohemian vibe.", facts:[{i:'fa-water',l:'Surf',v:'World-class'},{i:'fa-paw',l:'Wildlife',v:'Elephants nearby'},{i:'fa-sun',l:'Season',v:'May - Oct'}], chips:['Surfing','Pottuvil Lagoon','Elephant Rock','Baobab Tree'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Arugam-Bay_processed.webp' },
          { id:'bentota', cat:'beach', name:'Bentota & Beruwala', lat:6.4269, lng:80.0000, region:'Western Province', days:1, bestTime:'Nov - Apr', hint:'Water sports hub - turtle hatcheries - Madu River safari', tagline:"The water-sports capital of Sri Lanka -- Madu River mangrove safaris, sea turtle hatcheries and acclaimed Ayurveda wellness resorts.", facts:[{i:'fa-water',l:'Activities',v:'Water Sports'},{i:'fa-leaf',l:'Wellness',v:'Ayurveda'},{i:'fa-fish',l:'Conservation',v:'Sea Turtles'}], chips:['Water Sports','Turtle Hatchery','Madu River','Ayurveda Spa'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Bentota.webp' },
          { id:'hikkaduwa', cat:'beach', name:'Hikkaduwa', lat:6.1395, lng:80.1034, region:'Southern Province - West Coast', days:1, bestTime:'Nov - Apr', hint:'Coral reef snorkelling - sea turtles - lively surf town', tagline:"A vibrant beach town with Sri Lanka's most accessible coral reefs -- snorkel with sea turtles, surf beginner breaks and enjoy lively beach life.", facts:[{i:'fa-fish',l:'Highlight',v:'Coral Reef'},{i:'fa-water',l:'Surf',v:'Beginner Breaks'},{i:'fa-paw',l:'Wildlife',v:'Sea Turtles'}], chips:['Coral Reef','Sea Turtles','Surf Lessons','Beach Life'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Hikkaduwa_processed.webp' },
          { id:'tangalle', cat:'beach', name:'Tangalle', lat:6.0232, lng:80.7982, region:'Southern Province - Far South', days:1, bestTime:'Nov - Apr', hint:'Secluded unspoilt beaches - sea turtle nesting - peaceful far south', tagline:"Sri Lanka's most secluded south coast beach -- wide undeveloped shores, sea turtle nesting sites and tranquil rocky headland coves.", facts:[{i:'fa-umbrella-beach',l:'Vibe',v:'Wild & Secluded'},{i:'fa-paw',l:'Turtles',v:'Nesting Beach'},{i:'fa-sun',l:'Coast',v:'Far South'}], chips:['Turtle Nesting','Remote Coves','Rock Pools','Sunset Beach'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Tangalle_processed.webp' },
          { id:'negombo', cat:'beach', name:'Negombo Beach', lat:7.2092, lng:79.8380, region:'Western Province - Near Airport', days:1, bestTime:'Nov - Apr', hint:'Airport gateway beach - Dutch canals - fresh seafood', tagline:"The gateway beach to Sri Lanka -- a lively west coast strip with Dutch-built canals, fresh seafood restaurants and 20-minute airport access.", facts:[{i:'fa-plane',l:'Transfer',v:'12 km - 20 min'},{i:'fa-fish',l:'Famous For',v:'Fresh Seafood'},{i:'fa-water',l:'Feature',v:'Dutch Canals'}], chips:['Dutch Canals','Fish Market',"St. Mary's Church",'Lagoon Rides'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/negambo-beach_processed.webp' },
          { id:'ella', cat:'hills', name:'Ella & Nine Arches Bridge', lat:6.8667, lng:81.0466, region:'Uva Province - 1,041 m', days:2, bestTime:'Year-round', hint:'Nine Arches Bridge - Ella Rock - iconic scenic train', tagline:"Sri Lanka's most photogenic hill station -- the iconic Nine Arches Bridge, Ella Rock hike, tea valley vistas and the legendary scenic train journey.", facts:[{i:'fa-train',l:'Scenic Train',v:'From Nanu Oya'},{i:'fa-mountain',l:'Hike',v:'Ella Rock'},{i:'fa-bridge',l:'Icon',v:'Nine Arches'}], chips:['Nine Arches Bridge','Ella Rock Hike','Tea Valleys','Little Adams Peak'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Ella-Nine-Arch-Bridge_processed.webp' },
          { id:'nuwaraeliya', cat:'hills', name:'Nuwara Eliya', lat:6.9497, lng:80.7891, region:'Central Province - 1,868 m', days:1, bestTime:'Year-round', hint:"'Little England' - Ceylon tea capital - 1,868 m coolness", tagline:"Sri Lanka's 'Little England' at 1,868 m -- rolling tea estates, colonial bungalows, misty strawberry farms and world-famous Ceylon tea factory tours.", facts:[{i:'fa-mountain',l:'Altitude',v:'1,868 m'},{i:'fa-mug-hot',l:'Famous For',v:'Ceylon Tea'},{i:'fa-thermometer-half',l:'Climate',v:'Cool & Misty'}], chips:['Tea Plantation Tour','Factory Visit','Gregory Lake','Colonial Town'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Nuwara-Eliya_processed.webp' },
          { id:'hortonplains', cat:'hills', name:"Horton Plains & World's End", lat:6.8024, lng:80.8024, region:'Central Province - 2,100 m', days:0.5, bestTime:'Jan - Mar', hint:"World's End -- an 870 m sheer drop from cloud forest", tagline:"A cloud-forest plateau at 2,100 m -- misty trails lead to World's End, an 870-metre sheer escarpment drop into the southern plains below.", facts:[{i:'fa-mountain',l:'Altitude',v:'2,100 m'},{i:'fa-wind',l:'Feature',v:"World's End"},{i:'fa-paw',l:'Wildlife',v:'Sambar Deer'}], chips:["World's End","Baker's Falls",'Cloud Forest','Sambar Deer'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Horton-Plains_processed.webp' },
          { id:'adamspeak', cat:'hills', name:"Adam's Peak (Sri Pada)", lat:6.8096, lng:80.4997, region:'Sabaragamuwa - 2,243 m', days:1, bestTime:'Dec - May', hint:'Sacred pilgrimage mountain - night climb - spectacular sunrise', tagline:"Sri Lanka's most sacred pilgrimage peak at 2,243 m -- a night climb of 5,500 steps to watch the sunrise over a sea of clouds.", facts:[{i:'fa-mountain',l:'Altitude',v:'2,243 m'},{i:'fa-road',l:'Steps',v:'5,500+'},{i:'fa-sun',l:'Highlight',v:'Summit Sunrise'}], chips:['Night Pilgrimage','Summit Sunrise','Sacred Footprint','Tea Estates'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Adams-Peak_processed.webp' },
          { id:'haputale', cat:'hills', name:"Haputale & Lipton's Seat", lat:6.7688, lng:80.9542, region:'Uva Province - 1,431 m', days:0.5, bestTime:'Year-round', hint:"Lipton's Seat - dramatic ridge views - authentic tea country", tagline:"A hidden hilltop village perched on a ridge -- visit Lipton's Seat where Thomas Lipton surveyed his vast tea empire stretching to the horizon.", facts:[{i:'fa-mountain',l:'Altitude',v:'1,431 m'},{i:'fa-mug-hot',l:'Legend',v:"Lipton's Seat"},{i:'fa-eye',l:'Views',v:'360 deg Panorama'}], chips:["Lipton's Seat",'Tea Estates','Scenic Train Stop','Ridge Walk'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/alla-hike.webp' },
          { id:'knuckles', cat:'hills', name:'Knuckles Mountain Range', lat:7.4167, lng:80.7500, region:'Central Province', days:2, bestTime:'Year-round', hint:'Cloud-forest trekking - village homestays - endemic wildlife', tagline:"A dramatic cloud-kissed mountain range with multi-day trekking trails through misty valleys, waterfalls, village homestays and extraordinary biodiversity.", facts:[{i:'fa-hiking',l:'Trekking',v:'Multi-day Routes'},{i:'fa-water',l:'Features',v:'Cloud & Waterfalls'},{i:'fa-paw',l:'Wildlife',v:'Endemic Species'}], chips:['Multi-day Trek','Waterfalls','Village Homestays','Cloud Mist'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Knuckles-Mountain_processed.webp' },
          { id:'colombo', cat:'culture', name:'Colombo', lat:6.9271, lng:79.8612, region:'Western Province - Capital', days:1, bestTime:'Year-round', hint:'Vibrant capital - colonial forts - Pettah market - world-class food', tagline:"A cosmopolitan port capital blending Dutch colonial history, buzzing Pettah market, world-class dining, vibrant street art and a rapidly evolving skyline.", facts:[{i:'fa-city',l:'Population',v:'752,000+'},{i:'fa-plane',l:'Gateway',v:"Int'l Hub"},{i:'fa-utensils',l:'Food',v:'World-class'}], chips:['Galle Face Green','Pettah Market','Gangaramaya Temple','Dutch Hospital'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/colombo_processed.webp' },
          { id:'kandy-city', cat:'culture', name:'Kandy City', lat:7.2906, lng:80.6337, region:'Central Province - 500 m', days:2, bestTime:'Year-round', hint:'Cultural capital - Kandy Lake - Perahera festival - botanical gardens', tagline:"Sri Lanka's cultural heart -- a lakeside hill city with the Esala Perahera festival, a vibrant arts scene and the Royal Botanical Gardens.", facts:[{i:'fa-water',l:'Feature',v:'Kandy Lake'},{i:'fa-theater-masks',l:'Festival',v:'Esala Perahera'},{i:'fa-leaf',l:'Gardens',v:'Royal Botanic'}], chips:['Kandy Lake Walk','Cultural Show','Royal Botanical Gardens','Perahera Festival'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/kandy_processed.webp' },
          { id:'jaffna', cat:'culture', name:'Jaffna', lat:9.6615, lng:80.0255, region:'Northern Province', days:2, bestTime:'May - Sep', hint:'Tamil cultural capital - ancient temples - Jaffna Fort - unique cuisine', tagline:"Sri Lanka's vibrant Tamil cultural heart -- ancient Nainativu temple, magnificent Jaffna Fort, spicy northern cuisine and a resilient, welcoming community.", facts:[{i:'fa-place-of-worship',l:'Famous For',v:'Hindu Temples'},{i:'fa-utensils',l:'Cuisine',v:'Spicy Northern'},{i:'fa-fort-awesome',l:'Landmark',v:'Jaffna Fort'}], chips:['Nainativu Temple','Jaffna Fort','Nagadeepa Island','Traditional Cuisine'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Jaffna_processed.webp' },
          { id:'galle-city', cat:'culture', name:'Galle (Town)', lat:6.0535, lng:80.2210, region:'Southern Province', days:0.5, bestTime:'Nov - Apr', hint:'Beyond the fort - local markets - southern cultural hub', tagline:"Beyond the fort walls lies a vibrant town with fresh fish markets, colonial architecture, local cafes and the relaxed character of Sri Lanka's south.", facts:[{i:'fa-fish',l:'Market',v:'Fresh Fish'},{i:'fa-utensils',l:'Food',v:'Coastal Cuisine'},{i:'fa-city',l:'Character',v:'Relaxed South'}], chips:['Fish Market','Local Cafes','Colonial Architecture','South Coast Vibe'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Galle-City_processed.webp' },
          { id:'mannar', cat:'culture', name:'Mannar Island', lat:8.9781, lng:79.9045, region:'Northern Province', days:1, bestTime:'May - Sep', hint:'Remote island - ancient baobab trees - flamingos - Adams Bridge', tagline:"A remote, otherworldly island connected by causeway -- ancient baobab trees, Adam's Bridge ruins, flamingo flocks and an off-the-beaten-path atmosphere.", facts:[{i:'fa-tree',l:'Famous For',v:'Baobab Trees'},{i:'fa-dove',l:'Wildlife',v:'Flamingos'},{i:'fa-water',l:'Feature',v:"Adam's Bridge"}], chips:['Ancient Baobabs','Flamingo Flocks',"Adam's Bridge",'Mannar Fort'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/mannar-island.webp' },
          { id:'village-cycling', cat:'experience', name:'Sigiriya Village Cycling', lat:7.9500, lng:80.7450, region:'Cultural Triangle - Village Life', days:0.5, bestTime:'Year-round', hint:'Paddy field cycling - catamaran ride - village home-cooked lunch', tagline:"An immersive rural cycling experience through paddy fields, ancient irrigation canals and tiny villages -- ending with a traditional home-cooked lunch.", facts:[{i:'fa-bicycle',l:'Activity',v:'Village Cycling'},{i:'fa-water',l:'Bonus',v:'Catamaran Ride'},{i:'fa-utensils',l:'Lunch',v:'Village Meal'}], chips:['Paddy Field Cycling','Catamaran Ride','Village Lunch','Irrigation Canals'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/sigiriya-village-cyling_processed.webp' },
          { id:'pigeon-island', cat:'experience', name:'Pigeon Island Marine Park', lat:8.6200, lng:81.2400, region:'Eastern Province - Off Nilaveli', days:0.5, bestTime:'May - Sep', hint:'Blacktip reef sharks - hawksbill turtles - coral gardens', tagline:"Sri Lanka's premier marine national park -- vibrant coral gardens, blacktip reef sharks, hawksbill turtles and over 300 tropical fish species.", facts:[{i:'fa-fish',l:'Fish',v:'300+ species'},{i:'fa-water',l:'Highlight',v:'Blacktip Sharks'},{i:'fa-paw',l:'Turtles',v:'Hawksbill'}], chips:['Reef Sharks','Coral Gardens','Sea Turtles','Snorkelling'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/pigeon-island_processed.webp' },
          { id:'lipton-seat', cat:'experience', name:"Lipton's Seat -- Dambatenne", lat:6.7900, lng:81.0300, region:'Uva Province - Tea Country', days:0.5, bestTime:'Year-round', hint:"Thomas Lipton's viewpoint - uninterrupted tea estate panoramas", tagline:"Hike through rolling tea estates to the famous viewpoint where Sir Thomas Lipton surveyed his vast tea empire -- vast uninterrupted views over Uva province.", facts:[{i:'fa-mug-hot',l:'Legend',v:'Thomas Lipton'},{i:'fa-eye',l:'Views',v:'360 deg Panorama'},{i:'fa-hiking',l:'Hike',v:'Through Tea'}], chips:['Tea Estate Walk','Panoramic Views','Colonial History','Photography'], img:'https://pixelceylontours.com/wp-content/uploads/2026/03/Haputale-Liptons-Seat_processed.webp' }
        ];

        let itinerary = [];
        let activeFilter = 'all';
        let openSpotId = null;
        let map, markerMap = {}, routeLine, routeDecorations = [];
        let dragSrc = null;
        // ─── NEW: View Route toggle state ───
        let routeOnlyMode = false;

        function haversine(la1, ln1, la2, ln2){
          const R=6371,dLa=(la2-la1)*Math.PI/180,dLn=(ln2-ln1)*Math.PI/180;
          const a=Math.sin(dLa/2)**2 + Math.cos(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.sin(dLn/2)**2;
          return R*2*Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        }
        function roadKm(la1,ln1,la2,ln2){ return haversine(la1,ln1,la2,ln2)*1.38; }
        function fmtTime(km){
          const h = km/48;
          if(h<1) return Math.round(h*60)+' min';
          const hh=Math.floor(h), mm=Math.round((h%1)*60);
          return mm ? hh+'h '+mm+'m' : hh+'h';
        }
        function routeTotalKm(){
          let t=0;
          for(let i=0;i<itinerary.length-1;i++){
            const a=byId(itinerary[i]), b=byId(itinerary[i+1]);
            if(a&&b) t += roadKm(a.lat,a.lng,b.lat,b.lng);
          }
          return Math.round(t);
        }
        function routeTotalDays(){
          return itinerary.reduce((s,id)=>{ const sp=byId(id); return s + (sp ? sp.days : 0); },0);
        }
        function bearing(la1,ln1,la2,ln2){
          const dLn=(ln2-ln1)*Math.PI/180;
          const y=Math.sin(dLn)*Math.cos(la2*Math.PI/180);
          const x=Math.cos(la1*Math.PI/180)*Math.sin(la2*Math.PI/180)-Math.sin(la1*Math.PI/180)*Math.cos(la2*Math.PI/180)*Math.cos(dLn);
          return Math.atan2(y,x)*180/Math.PI;
        }
        const byId = id => SPOTS.find(s => s.id===id);
        const isMob = () => window.innerWidth <= 900;

        function initMap(){
          map = L.map(els.mapEl, {center:[7.87,80.65], zoom:7, zoomControl:false, scrollWheelZoom:false});
          L.control.zoom({position:'bottomright'}).addTo(map);
          L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom:19
          }).addTo(map);

          routeLine = L.polyline([], {
            color:'#f5a100',
            weight:2.5,
            opacity:.7,
            dashArray:'10 7',
            lineCap:'round',
            lineJoin:'round'
          }).addTo(map);

          els.mapEl.addEventListener('click', ()=> map.scrollWheelZoom.enable());
          els.mapEl.addEventListener('mouseleave', ()=> map.scrollWheelZoom.disable());

          renderMarkers();
          setTimeout(()=> map.invalidateSize(), 300);
        }

        function mkIcon(spot, inItin, num){
          const c = inItin ? '#f5a100' : CAT[spot.cat].color;
          const sz = inItin ? 40 : 34;
          const h = sz/2;
          const inner = inItin
            ? `<div class="slm-body num" style="width:${sz}px;height:${sz}px;background:${c}">${num}</div>`
            : `<div class="slm-body" style="width:${sz}px;height:${sz}px;background:${c}"><i class="fas ${CAT[spot.cat].icon}"></i></div>`;
          return L.divIcon({
            html:`<div class="slm ${inItin ? 'in-itin' : ''}" style="width:${sz}px;height:${sz}px;--mc:${c}">
                    <div class="slm-ring"></div>${inner}
                  </div>`,
            className:'',
            iconSize:[sz,sz],
            iconAnchor:[h,h],
            popupAnchor:[0,-(h+4)]
          });
        }

        // ─── UPDATED: renderMarkers respects routeOnlyMode ───
        function renderMarkers(){
          Object.values(markerMap).forEach(({marker})=>{ if(map.hasLayer(marker)) map.removeLayer(marker); });
          markerMap = {};
          let count = 0;

          SPOTS.forEach(spot=>{
            const inIt = itinerary.includes(spot.id);
            // In routeOnlyMode, only show itinerary spots; otherwise respect filter
            const show = routeOnlyMode
              ? inIt
              : (activeFilter === 'all' || spot.cat === activeFilter);

            const num = inIt ? itinerary.indexOf(spot.id) + 1 : 0;

            const m = L.marker([spot.lat, spot.lng], {
              icon: mkIcon(spot, inIt, num),
              title: spot.name,
              zIndexOffset: inIt ? 600 : 0
            });

            m.on('mouseover', e => showTT(spot, e.originalEvent));
            m.on('mousemove', e => moveTT(e.originalEvent));
            m.on('mouseout', () => hideTT());
            m.on('click', () => openDetail(spot.id));

            markerMap[spot.id] = { marker: m };
            if(show){ m.addTo(map); count++; }
          });

          els.countNum.textContent = count;
        }

        function updateRoute(){
          routeDecorations.forEach(d => { if(map.hasLayer(d)) map.removeLayer(d); });
          routeDecorations = [];

          if(itinerary.length < 2){
            routeLine.setLatLngs([]);
            return;
          }

          const spots = itinerary.map(byId).filter(Boolean);
          routeLine.setLatLngs(spots.map(s => [s.lat, s.lng]));

          spots.forEach((sp, i)=>{
            if(i === spots.length - 1) return;
            const nx = spots[i+1];
            const mid = [(sp.lat + nx.lat)/2, (sp.lng + nx.lng)/2];
            const brn = bearing(sp.lat, sp.lng, nx.lat, nx.lng);
            const km = roadKm(sp.lat, sp.lng, nx.lat, nx.lng);

            const arrowIc = L.divIcon({
              html:`<div style="transform:rotate(${brn}deg);color:#f5a100;font-size:9px;opacity:.7">&#9654;</div>`,
              className:'',
              iconSize:[18,18],
              iconAnchor:[9,9]
            });

            const lblIc = L.divIcon({
              html:`<div class="drive-lbl">~${fmtTime(km)} - ${Math.round(km)} km</div>`,
              className:'',
              iconSize:[120,22],
              iconAnchor:[60,11]
            });

            const off = [(sp.lat+nx.lat)/2 + .018, (sp.lng+nx.lng)/2];
            const am = L.marker(mid, {icon:arrowIc, interactive:false, zIndexOffset:200});
            const lm = L.marker(off, {icon:lblIc, interactive:false, zIndexOffset:100});

            am.addTo(map);
            lm.addTo(map);
            routeDecorations.push(am, lm);
          });
        }

        function addStop(id){ if(!itinerary.includes(id)){ itinerary.push(id); refreshAll(); } }
        function removeStop(id){
          itinerary = itinerary.filter(x => x !== id);
          if(openSpotId === id) closeDetail();
          // If no stops left, exit route-only mode
          if(itinerary.length === 0 && routeOnlyMode){
            routeOnlyMode = false;
            updateViewRouteBtn();
          }
          refreshAll();
        }
        function toggleStop(id){ itinerary.includes(id) ? removeStop(id) : addStop(id); }

        function refreshAll(){
          renderMarkers();
          updateRoute();
          renderItin();
          updateStats();
          if(openSpotId) refreshDetailBtn();
          els.btnFitMap.classList.toggle('show', itinerary.length > 0);
        }

        function updateStats(){
          const n = itinerary.length;
          const days = routeTotalDays();
          const km = n > 1 ? routeTotalKm() : 0;

          els.statStops.textContent = n || '0';
          els.statDays.textContent = n ? (days < 1 ? '<1' : Math.ceil(days)) : '--';
          els.statKm.textContent = km ? km : '--';

          els.statDays.className = 'sbs-val' + (n ? '' : ' dim');
          els.statKm.className = 'sbs-val' + (km ? '' : ' dim');

          if(n > 1){
            els.totalWrap.style.display = 'flex';
            els.totalLabel.textContent = km + ' km - ' + fmtTime(km) + ' total driving';
          } else {
            els.totalWrap.style.display = 'none';
          }

          els.mobCount.textContent = n ? n + ' stop' + (n > 1 ? 's' : '') : '0 stops';
        }

        function renderItin(){
          els.itinList.innerHTML = '';

          if(!itinerary.length){
            els.emptyState.classList.add('vis');
            return;
          }
          els.emptyState.classList.remove('vis');

          itinerary.forEach((id, idx)=>{
            const sp = byId(id);
            if(!sp) return;
            const c = CAT[sp.cat];

            if(idx > 0){
              const prev = byId(itinerary[idx-1]);
              const km = prev ? Math.round(roadKm(prev.lat, prev.lng, sp.lat, sp.lng)) : 0;
              const conn = document.createElement('div');
              conn.className = 'itin-conn';
              conn.innerHTML = `<div class="itin-conn-line"></div><span style="white-space:nowrap">~${km} km</span><div class="itin-conn-line"></div>`;
              els.itinList.appendChild(conn);
            }

            const item = document.createElement('div');
            item.className = 'itin-item';
            item.draggable = true;
            item.dataset.id = id;
            item.dataset.idx = idx;

            const driveStr = idx > 0 ? (() => {
              const prev = byId(itinerary[idx-1]);
              return prev ? `<span class="itin-drive"><i class="fas fa-clock"></i>${fmtTime(roadKm(prev.lat, prev.lng, sp.lat, sp.lng))}</span>` : '';
            })() : '';

            item.innerHTML = `
              <div class="itin-handle" title="Drag to reorder"><span></span><span></span><span></span></div>
              <div class="itin-num">${idx+1}</div>
              <div class="itin-info">
                <div class="itin-name">${sp.name}</div>
                <div class="itin-meta">
                  <span class="itin-cat" style="color:${c.color};border-color:${c.color}28;background:${c.color}12">${c.label}</span>
                  ${driveStr}
                </div>
              </div>
              <div class="itin-days"><i class="fas fa-sun" style="font-size:.46rem;opacity:.55"></i>${sp.days < 1 ? '&frac12;' : Math.ceil(sp.days)}d</div>
              <button class="itin-rm" title="Remove"><i class="fas fa-times"></i></button>
            `;

            item.querySelector('.itin-rm').addEventListener('click', function(e){
              e.stopPropagation();
              removeStop(id);
            });

            item.addEventListener('click', function(e){
              if(e.target.closest('.itin-rm,.itin-handle')) return;
              map.setView([sp.lat, sp.lng], 10, {animate:true, duration:.6});
              openDetail(id);
            });

            item.addEventListener('dragstart', function(e){
              dragSrc = idx;
              e.dataTransfer.effectAllowed = 'move';
              setTimeout(()=> item.classList.add('dragging'), 0);
            });
            item.addEventListener('dragend', function(){ item.classList.remove('dragging'); });
            item.addEventListener('dragover', function(e){ e.preventDefault(); item.classList.add('drag-over'); });
            item.addEventListener('dragleave', function(){ item.classList.remove('drag-over'); });
            item.addEventListener('drop', function(e){
              e.preventDefault();
              item.classList.remove('drag-over');
              if(dragSrc !== null && dragSrc !== idx){
                const moved = itinerary.splice(dragSrc, 1)[0];
                itinerary.splice(idx, 0, moved);
                refreshAll();
              }
              dragSrc = null;
            });

            els.itinList.appendChild(item);
          });
        }

        function showTT(spot, e){
          if(isMob()) return;
          els.ttImg.src = spot.img;
          els.ttCd.style.background = CAT[spot.cat].color;
          els.ttCl.textContent = CAT[spot.cat].label;
          els.ttCl.style.color = CAT[spot.cat].color;
          els.ttName.textContent = spot.name;
          els.ttHint.textContent = spot.hint;
          els.ttDays.innerHTML = `<i class="fas fa-moon" style="color:var(--orange)"></i>&ensp;${spot.days < 1 ? 'Half' : Math.ceil(spot.days)} recommended day${spot.days > 1 ? 's' : ''}`;
          els.tooltip.style.display = 'block';
          moveTT(e);
        }
        function moveTT(e){
          els.tooltip.style.left = e.clientX + 'px';
          els.tooltip.style.top = e.clientY + 'px';
        }
        function hideTT(){ els.tooltip.style.display = 'none'; }

        function openDetail(id){
          const sp = byId(id);
          if(!sp) return;
          openSpotId = id;
          hideTT();

          const inIt = itinerary.includes(id);
          const c = CAT[sp.cat];
          const facts = sp.facts.map(f => `<div class="dp-fact"><i class="fas ${f.i}"></i><strong>${f.v}</strong>&ensp;${f.l}</div>`).join('');
          const chips = sp.chips.map(ch => `<div class="dp-chip">${ch}</div>`).join('');

          els.detailPanel.innerHTML = `
            <button class="dp-close"><i class="fas fa-times"></i></button>
            <div class="dp-img">
              <img src="${sp.img}" alt="${sp.name}" loading="lazy">
              <div class="dp-catbadge"><div class="dp-catdot" style="background:${c.color}"></div><span style="color:${c.color}">${c.label}</span></div>
              <div class="dp-region"><i class="fas fa-map-marker-alt"></i>${sp.region}</div>
            </div>
            <div class="dp-body">
              <div class="dp-name">${sp.name}</div>
              <div class="dp-tagline">${sp.tagline}</div>
              <div class="dp-facts">${facts}</div>
              <div class="dp-best">
                <i class="fas fa-calendar-alt"></i>
                <div class="dp-best-txt">
                  <strong>Best Time - ${sp.bestTime}</strong>
                  Recommended stay: ${sp.days < 1 ? 'Half day' : Math.ceil(sp.days) + ' day' + (sp.days > 1 ? 's' : '')}
                </div>
              </div>
              <div class="dp-chips">${chips}</div>
              <div class="dp-cta">
                <button class="dp-addbtn ${inIt ? 'remove' : 'add'}" id="dp-addbtn">
                  <i class="fas ${inIt ? 'fa-times-circle' : 'fa-plus-circle'}"></i>
                  ${inIt ? 'Remove from Route' : 'Add to My Route'}
                </button>
              </div>
            </div>
          `;

          els.detailPanel.querySelector('.dp-close').addEventListener('click', closeDetail);
          els.detailPanel.querySelector('#dp-addbtn').addEventListener('click', function(){
            toggleStop(id);
          });

          els.detailPanel.classList.add('vis');
          if(!isMob()) map.panTo([sp.lat, sp.lng], {animate:true, duration:.5});
        }

        function refreshDetailBtn(){
          const btn = els.detailPanel.querySelector('#dp-addbtn');
          if(!btn || !openSpotId) return;
          const inIt = itinerary.includes(openSpotId);
          btn.className = 'dp-addbtn ' + (inIt ? 'remove' : 'add');
          btn.innerHTML = `<i class="fas ${inIt ? 'fa-times-circle' : 'fa-plus-circle'}"></i> ${inIt ? 'Remove from Route' : 'Add to My Route'}`;
          btn.onclick = ()=> toggleStop(openSpotId);
        }

        function closeDetail(){
          openSpotId = null;
          els.detailPanel.classList.remove('vis');
          setTimeout(()=>{ els.detailPanel.innerHTML = ''; }, 350);
        }

        els.filterBtns.forEach(btn => {
          btn.addEventListener('click', function(){
            // Clicking a filter exits route-only mode
            if(routeOnlyMode){
              routeOnlyMode = false;
              updateViewRouteBtn();
            }
            els.filterBtns.forEach(b => b.classList.remove('on'));
            btn.classList.add('on');
            activeFilter = btn.dataset.cat;
            renderMarkers();
          });
        });

        els.btnOptimize.addEventListener('click', function(){
          if(itinerary.length < 3) return;
          const rem = [...itinerary];
          const result = [rem.shift()];
          while(rem.length){
            const last = byId(result[result.length - 1]);
            let bi = 0, bd = Infinity;
            rem.forEach((id, i) => {
              const sp = byId(id);
              if(!sp) return;
              const d = haversine(last.lat, last.lng, sp.lat, sp.lng);
              if(d < bd){ bd = d; bi = i; }
            });
            result.push(rem.splice(bi, 1)[0]);
          }
          itinerary = result;
          refreshAll();
          fitRoute();
        });

        els.btnClear.addEventListener('click', function(){
          if(!itinerary.length || !confirm('Clear your entire itinerary?')) return;
          itinerary = [];
          openSpotId = null;
          closeDetail();
          // Exit route-only mode on clear
          if(routeOnlyMode){
            routeOnlyMode = false;
            updateViewRouteBtn();
          }
          refreshAll();
        });

        function fitRoute(){
          if(!itinerary.length) return;
          const spots = itinerary.map(byId).filter(Boolean);
          if(spots.length === 1){
            map.setView([spots[0].lat, spots[0].lng], 10, {animate:true});
            return;
          }
          const lats = spots.map(s => s.lat), lngs = spots.map(s => s.lng);
          map.fitBounds([
            [Math.min(...lats)-.3, Math.min(...lngs)-.4],
            [Math.max(...lats)+.3, Math.max(...lngs)+.4]
          ], {animate:true, duration:.8, padding:[40,40]});
        }

        // ─── NEW: View Route button logic ───
        function updateViewRouteBtn(){
          if(routeOnlyMode){
            els.btnViewRoute.classList.add('active');
            els.btnViewRoute.innerHTML = '<i class="fas fa-eye-slash"></i> Show All';
            els.routeModeBadge.classList.add('show');
          } else {
            els.btnViewRoute.classList.remove('active');
            els.btnViewRoute.innerHTML = '<i class="fas fa-route"></i> View Route';
            els.routeModeBadge.classList.remove('show');
          }
        }

        els.btnViewRoute.addEventListener('click', function(){
          if(!itinerary.length){
            // Nothing to show — flash hint
            const tip = root.querySelector('.sb-empty-tip');
            if(tip){
              tip.style.color = 'var(--teal-l)';
              setTimeout(()=> tip.style.color = '', 800);
            }
            return;
          }
          // Toggle route-only mode
          routeOnlyMode = !routeOnlyMode;
          updateViewRouteBtn();
          renderMarkers();
          if(routeOnlyMode) fitRoute();
        });

        els.btnFitMap.addEventListener('click', fitRoute);

        els.btnEnquire.addEventListener('click', openModal);

        // ─── UPDATED: renderModalStops — with remove buttons ───
        function renderModalStops(){
          const spots = itinerary.map(byId).filter(Boolean);
          els.modalStops.innerHTML = spots.map((sp, i) => {
            const c = CAT[sp.cat];
            return `<div class="modal-stop" data-id="${sp.id}">
              <div class="modal-stop-num">${i+1}</div>
              <span style="color:${c.color};font-size:.52rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase">${sp.name}</span>
              <button class="modal-stop-rm" title="Remove ${sp.name}"><i class="fas fa-times"></i></button>
            </div>`;
          }).join('');

          // Attach remove handlers
          els.modalStops.querySelectorAll('.modal-stop-rm').forEach(function(btn){
            btn.addEventListener('click', function(e){
              e.stopPropagation();
              const id = btn.closest('.modal-stop').dataset.id;
              removeStop(id);
              // If no stops remain, close modal
              if(itinerary.length === 0){
                closeModal();
                return;
              }
              // Otherwise re-render the summary section only
              renderModalStops();
              updateModalMeta();
            });
          });
        }

        function updateModalMeta(){
          const spots = itinerary.map(byId).filter(Boolean);
          const km = routeTotalKm();
          const days = Math.ceil(routeTotalDays());
          els.modalMeta.innerHTML = `
            <span><i class="fas fa-map-marker-alt"></i>${spots.length} destination${spots.length > 1 ? 's' : ''}</span>
            <span><i class="fas fa-sun"></i>~${days} day${days > 1 ? 's' : ''} recommended</span>
            ${km ? `<span><i class="fas fa-road"></i>~${km} km total</span>` : ''}
          `;
        }

        function openModal(){
          if(!itinerary.length){
            const tip = root.querySelector('.sb-empty-tip');
            if(tip){
              tip.style.color = 'var(--orange-l)';
              setTimeout(()=> tip.style.color = '', 800);
            }
            return;
          }

          renderModalStops();
          updateModalMeta();

          els.modalMain.style.display = '';
          els.modalSuccess.classList.remove('show');
          els.enquiryForm.reset();
          root.querySelectorAll('.form-input,.form-select,.form-textarea').forEach(el => el.style.borderColor = '');
          els.modalOverlay.classList.add('open');
          document.body.style.overflow = 'hidden';
        }

        function closeModal(){
          els.modalOverlay.classList.remove('open');
          document.body.style.overflow = '';
        }

        els.modalClose.addEventListener('click', closeModal);
        els.successBack.addEventListener('click', closeModal);

        function submitEnquiry(e){
          e.preventDefault();

          const form = els.enquiryForm;
          const btn  = els.submitBtn;

          // Client-side validation
          let valid = true;
          form.querySelectorAll('[required]').forEach(function(el){
            el.style.borderColor = '';
            if(!el.value.trim()){
              el.style.borderColor = '#e07070';
              if(valid) el.focus();
              valid = false;
            }
          });
          if(!valid) return;

          btn.disabled = true;
          btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&ensp;Sending...';

          // Collect values
          const stops      = itinerary.map(byId).filter(Boolean);
          const km         = routeTotalKm();
          const days       = Math.ceil(routeTotalDays());
          const now        = new Date();

          // Build itinerary payload for PHP (only the fields PHP needs)
          const itinPayload = stops.map(function(s){
            return { id: s.id, name: s.name, region: s.region, cat: s.cat, days: s.days };
          });

          // POST to WordPress AJAX handler (no third-party service)
          const fd = new FormData();
          fd.append('action',     'pctp_send_enquiry');
          fd.append('nonce',      PCTP_AJAX_NONCE);
          fd.append('fname',      form.fname.value.trim());
          fd.append('lname',      form.lname.value.trim());
          fd.append('email',      form.email.value.trim());
          fd.append('phone',      form.phone.value.trim() || 'Not provided');
          fd.append('month',      form.month.value);
          fd.append('travellers', form.travellers.value);
          fd.append('budget',     form.budget.value     || 'Not specified');
          fd.append('duration',   form.duration.value   || 'Not specified');
          fd.append('message',    form.message.value.trim());
          fd.append('total_days', days);
          fd.append('total_km',   km);
          fd.append('itinerary',  JSON.stringify(itinPayload));

          fetch(PCTP_AJAX_URL, { method:'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data){
              if(data.success){
                els.modalMain.style.display = 'none';
                els.modalSuccess.classList.add('show');
              } else {
                throw new Error(data.data && data.data.message ? data.data.message : 'Send failed.');
              }
            })
            .catch(function(err){
              console.warn('Enquiry error:', err);
              let errDiv = root.querySelector('.submit-error');
              if(!errDiv){
                errDiv = document.createElement('div');
                errDiv.className = 'submit-error';
                errDiv.style.cssText = 'margin-top:.5rem;padding:.55rem .85rem;background:rgba(210,55,55,.1);border:1px solid rgba(210,55,55,.3);border-radius:2px;font-size:.68rem;color:#e07070;text-align:center';
                els.submitBtn.parentNode.appendChild(errDiv);
              }
              errDiv.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:5px"></i>' + (err.message || 'Could not send. Please try again or email us directly.');
              setTimeout(()=>{ if(errDiv.parentNode) errDiv.remove(); }, 6000);
            })
            .finally(function(){
              btn.disabled = false;
              btn.innerHTML = '<i class="fas fa-paper-plane"></i>&ensp;Send Enquiry';
            });
        }

        els.enquiryForm.addEventListener('submit', submitEnquiry);
        els.submitBtn.addEventListener('click', submitEnquiry);

        function toggleMobile(){
          const open = els.sidebar.classList.toggle('open');
          els.mobOverlay.classList.toggle('show', open);
        }
        function closeMobilePanel(){
          els.sidebar.classList.remove('open');
          els.mobOverlay.classList.remove('show');
        }

        els.mobToggle.addEventListener('click', toggleMobile);
        els.mobOverlay.addEventListener('click', closeMobilePanel);

        document.addEventListener('keydown', function(e){
          if(e.key === 'Escape'){
            if(els.modalOverlay.classList.contains('open')){
              closeModal();
              return;
            }
            closeDetail();
          }
        });

        els.modalOverlay.addEventListener('click', function(e){
          if(e.target === els.modalOverlay) closeModal();
        });

        initMap();
        updateStats();
        refreshAll();
      })();
      </script>
    </div>
    <?php
    return ob_get_clean();
});
