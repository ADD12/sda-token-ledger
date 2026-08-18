<?php
/**
 * Genesis Bonus — award 1,000 SDA to new accounts from the 101DAO Genesis server.
 *
 * How it works:
 *  1. Admin sets a secret Genesis Invite Code and bonus amount in SDG Settings.
 *  2. The WordPress registration form gains an optional "Genesis Invite Code" field.
 *  3. On successful registration, if the submitted code matches, SDA_Token::issue_sda()
 *     is called automatically with the built-in GENESIS project / BID.
 *  4. A user-meta flag (sda_genesis_granted) prevents double-awarding.
 *  5. Admins can also grant the bonus manually from the Genesis Grants page.
 *
 * Compatible: PHP 7.2+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_Genesis {

    const DEFAULT_AMOUNT = 1000;
    const DEFAULT_PID    = 'GENESIS-101DAO';
    const DEFAULT_BID    = 'SDA-GENESIS-CHAIN';
    const META_GRANTED   = 'sda_genesis_granted';
    const META_DATE      = 'sda_genesis_granted_date';
    const META_LEDGER_ID = 'sda_genesis_ledger_id';

    public static function init() {
        // Registration form field
        add_action( 'register_form',       array( __CLASS__, 'registration_field' ) );
        // Validate code before creating user (adds error if invalid format)
        add_filter( 'registration_errors', array( __CLASS__, 'validate_registration' ), 10, 3 );
        // Award after user is created (same request — POST data still available)
        add_action( 'user_register',       array( __CLASS__, 'on_user_register' ), 10, 1 );
    }

    // ---------------------------------------------------------------- Settings helpers

    public static function get_code() {
        $s = get_option( 'sda_settings', array() );
        return isset( $s['genesis_code'] ) ? trim( $s['genesis_code'] ) : '';
    }

    public static function get_amount() {
        $s = get_option( 'sda_settings', array() );
        $v = isset( $s['genesis_amount'] ) ? (float) $s['genesis_amount'] : 0;
        return $v > 0 ? $v : self::DEFAULT_AMOUNT;
    }

    public static function get_pid() {
        $s = get_option( 'sda_settings', array() );
        $v = isset( $s['genesis_pid'] ) ? trim( $s['genesis_pid'] ) : '';
        return $v !== '' ? $v : self::DEFAULT_PID;
    }

    public static function get_bid() {
        $s = get_option( 'sda_settings', array() );
        $v = isset( $s['genesis_bid'] ) ? trim( $s['genesis_bid'] ) : '';
        return $v !== '' ? $v : self::DEFAULT_BID;
    }

    public static function code_is_set() {
        return self::get_code() !== '';
    }

    public static function code_matches( $submitted ) {
        $stored = self::get_code();
        if ( $stored === '' || $submitted === '' ) {
            return false;
        }
        return hash_equals( $stored, trim( $submitted ) );
    }

    // ---------------------------------------------------------------- Registration hooks

    /**
     * Render the invite-code field on the WordPress registration form.
     */
    public static function registration_field() {
        if ( ! self::code_is_set() ) {
            return; // Feature disabled — no code configured
        }
        $val = isset( $_POST['sda_genesis_code'] ) ? esc_attr( wp_unslash( $_POST['sda_genesis_code'] ) ) : '';
        ?>
        <p class="sda-genesis-field">
            <label for="sda_genesis_code">
                🌱 <?php esc_html_e( '101DAO Genesis Invite Code', 'sda-token-ledger' ); ?>
                <span style="font-size:12px;color:#666;display:block;margin-top:2px;">
                    <?php printf(
                        /* translators: %s = formatted number */
                        esc_html__( 'Members of the 101DAO Genesis server receive %s SDA on sign-up. Leave blank if you don\'t have a code.', 'sda-token-ledger' ),
                        '<strong>' . esc_html( number_format( self::get_amount(), 0 ) ) . '</strong>'
                    ); ?>
                </span>
            </label>
            <input type="text"
                   name="sda_genesis_code"
                   id="sda_genesis_code"
                   value="<?php echo $val; ?>"
                   class="input"
                   autocomplete="off"
                   spellcheck="false"
                   style="font-family:monospace">
        </p>
        <?php
    }

    /**
     * If a non-empty code is submitted but it does not match, add a registration error
     * so the user knows before their account is created.
     *
     * @param WP_Error $errors
     * @param string   $sanitized_user_login
     * @param string   $user_email
     * @return WP_Error
     */
    public static function validate_registration( $errors, $sanitized_user_login, $user_email ) {
        if ( ! self::code_is_set() ) {
            return $errors;
        }
        $submitted = isset( $_POST['sda_genesis_code'] ) ? sanitize_text_field( wp_unslash( $_POST['sda_genesis_code'] ) ) : '';
        if ( $submitted !== '' && ! self::code_matches( $submitted ) ) {
            $errors->add(
                'sda_genesis_code_invalid',
                '<strong>' . esc_html__( 'Error', 'sda-token-ledger' ) . '</strong>: ' .
                esc_html__( 'The Genesis Invite Code you entered is not valid. Leave it blank to register without the bonus.', 'sda-token-ledger' )
            );
        }
        return $errors;
    }

    /**
     * Called immediately after a new user is created.
     * Award the genesis bonus if the invite code was valid.
     *
     * @param int $user_id
     */
    public static function on_user_register( $user_id ) {
        if ( ! self::code_is_set() ) {
            return;
        }
        $submitted = isset( $_POST['sda_genesis_code'] ) ? sanitize_text_field( wp_unslash( $_POST['sda_genesis_code'] ) ) : '';
        if ( ! self::code_matches( $submitted ) ) {
            return;
        }
        self::award( $user_id, 'auto' );
    }

    // ---------------------------------------------------------------- Core award logic

    /**
     * Issue the genesis SDA bonus to a user.
     *
     * @param int    $user_id
     * @param string $source  'auto' (registration) | 'manual' (admin grant)
     * @return int|false  Ledger ID on success, false on failure or already awarded.
     */
    public static function award( $user_id, $source = 'manual' ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        // Prevent double-award
        if ( get_user_meta( $user_id, self::META_GRANTED, true ) ) {
            return false;
        }

        $ledger_id = SDA_Token::issue_sda( array(
            'user_id' => $user_id,
            'pid'     => self::get_pid(),
            'bid'     => self::get_bid(),
            'amount'  => (string) self::get_amount(),
            'sdg_goal'     => null,
            'tx_hash_side' => '',
            'notes'   => sprintf(
                '101DAO Genesis server welcome bonus (%s). Source: %s.',
                number_format( self::get_amount(), 0 ) . ' SDA',
                $source
            ),
        ) );

        if ( $ledger_id ) {
            update_user_meta( $user_id, self::META_GRANTED,   '1' );
            update_user_meta( $user_id, self::META_DATE,      current_time( 'mysql' ) );
            update_user_meta( $user_id, self::META_LEDGER_ID, $ledger_id );
            do_action( 'sda_genesis_awarded', $user_id, $ledger_id, $source );
        }

        return $ledger_id;
    }

    // ---------------------------------------------------------------- Query helpers

    /**
     * Return a count of users who have received the genesis bonus.
     *
     * @return int
     */
    public static function count_granted() {
        $users = get_users( array(
            'meta_key'   => self::META_GRANTED,
            'meta_value' => '1',
            'fields'     => 'ID',
            'number'     => -1,
        ) );
        return count( $users );
    }

    /**
     * Return recent genesis grant rows for the admin page.
     *
     * @param int $limit
     * @return array  Array of objects with user_id, display_name, user_email, granted_date, ledger_id.
     */
    public static function get_recent_grants( $limit = 50 ) {
        $users = get_users( array(
            'meta_key'   => self::META_GRANTED,
            'meta_value' => '1',
            'number'     => $limit,
            'orderby'    => 'meta_value',   // sorts on the meta value; date stored separately
            'order'      => 'DESC',
        ) );

        $result = array();
        foreach ( $users as $u ) {
            $obj               = new stdClass();
            $obj->user_id      = $u->ID;
            $obj->display_name = $u->display_name;
            $obj->user_email   = $u->user_email;
            $obj->granted_date = get_user_meta( $u->ID, self::META_DATE,      true );
            $obj->ledger_id    = get_user_meta( $u->ID, self::META_LEDGER_ID, true );
            $obj->has_granted  = true;
            $result[]          = $obj;
        }

        // Sort by date descending
        usort( $result, function( $a, $b ) {
            return strcmp( $b->granted_date, $a->granted_date );
        } );

        return $result;
    }

    /**
     * Has a specific user already received the genesis bonus?
     *
     * @param int $user_id
     * @return bool
     */
    public static function has_been_awarded( $user_id ) {
        return (bool) get_user_meta( absint( $user_id ), self::META_GRANTED, true );
    }
}
