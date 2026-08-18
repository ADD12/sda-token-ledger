<?php
/**
 * UN Sustainable Development Goals data.
 *
 * All 17 SDGs with colours, icons (emoji), and short descriptions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDA_SDGs {

    /** @return array<int, array{name: string, short: string, color: string, icon: string}> */
    public static function all(): array {
        return array(
            1  => array(
                'name'  => 'No Poverty',
                'short' => 'End poverty in all its forms everywhere.',
                'color' => '#E5243B',
                'icon'  => '🏠',
            ),
            2  => array(
                'name'  => 'Zero Hunger',
                'short' => 'End hunger, achieve food security and improved nutrition.',
                'color' => '#DDA63A',
                'icon'  => '🌾',
            ),
            3  => array(
                'name'  => 'Good Health and Well-Being',
                'short' => 'Ensure healthy lives and promote well-being for all.',
                'color' => '#4C9F38',
                'icon'  => '💊',
            ),
            4  => array(
                'name'  => 'Quality Education',
                'short' => 'Ensure inclusive, equitable quality education.',
                'color' => '#C5192D',
                'icon'  => '📚',
            ),
            5  => array(
                'name'  => 'Gender Equality',
                'short' => 'Achieve gender equality and empower all women and girls.',
                'color' => '#FF3A21',
                'icon'  => '⚥',
            ),
            6  => array(
                'name'  => 'Clean Water and Sanitation',
                'short' => 'Ensure availability and sustainable management of water.',
                'color' => '#26BDE2',
                'icon'  => '💧',
            ),
            7  => array(
                'name'  => 'Affordable and Clean Energy',
                'short' => 'Ensure access to affordable, reliable, sustainable energy.',
                'color' => '#FCC30B',
                'icon'  => '⚡',
            ),
            8  => array(
                'name'  => 'Decent Work and Economic Growth',
                'short' => 'Promote sustained, inclusive and sustainable economic growth.',
                'color' => '#A21942',
                'icon'  => '📈',
            ),
            9  => array(
                'name'  => 'Industry, Innovation and Infrastructure',
                'short' => 'Build resilient infrastructure and foster innovation.',
                'color' => '#FD6925',
                'icon'  => '🏗️',
            ),
            10 => array(
                'name'  => 'Reduced Inequalities',
                'short' => 'Reduce inequality within and among countries.',
                'color' => '#DD1367',
                'icon'  => '⚖️',
            ),
            11 => array(
                'name'  => 'Sustainable Cities and Communities',
                'short' => 'Make cities inclusive, safe, resilient and sustainable.',
                'color' => '#FD9D24',
                'icon'  => '🌆',
            ),
            12 => array(
                'name'  => 'Responsible Consumption and Production',
                'short' => 'Ensure sustainable consumption and production patterns.',
                'color' => '#BF8B2E',
                'icon'  => '♻️',
            ),
            13 => array(
                'name'  => 'Climate Action',
                'short' => 'Take urgent action to combat climate change and its impacts.',
                'color' => '#3F7E44',
                'icon'  => '🌍',
            ),
            14 => array(
                'name'  => 'Life Below Water',
                'short' => 'Conserve and sustainably use the oceans and marine resources.',
                'color' => '#0A97D9',
                'icon'  => '🐠',
            ),
            15 => array(
                'name'  => 'Life on Land',
                'short' => 'Protect, restore and promote sustainable use of terrestrial ecosystems.',
                'color' => '#56C02B',
                'icon'  => '🌳',
            ),
            16 => array(
                'name'  => 'Peace, Justice and Strong Institutions',
                'short' => 'Promote peaceful and inclusive societies for sustainable development.',
                'color' => '#00689D',
                'icon'  => '🕊️',
            ),
            17 => array(
                'name'  => 'Partnerships for the Goals',
                'short' => 'Strengthen the means of implementation and revitalize global partnership.',
                'color' => '#19486A',
                'icon'  => '🤝',
            ),
        );
    }

    /** Return a single SDG by number (1–17), or null if not found. */
    public static function get( int $number ): ?array {
        return self::all()[ $number ] ?? null;
    }

    /** Parse a stored comma-separated string into an array of goal numbers. */
    public static function parse_goals( string $goals_string ): array {
        if ( '' === trim( $goals_string ) ) {
            return array();
        }
        $raw    = array_map( 'intval', explode( ',', $goals_string ) );
        $result = array();
        foreach ( $raw as $n ) {
            if ( $n >= 1 && $n <= 17 ) {
                $result[] = $n;
            }
        }
        return $result;
    }

    /** Render a compact badge for a single SDG. */
    public static function badge( int $number ): string {
        $sdg = self::get( $number );
        if ( ! $sdg ) {
            return '';
        }
        return sprintf(
            '<span class="sda-sdg-badge" style="background:%s" title="%s">%s SDG %d</span>',
            esc_attr( $sdg['color'] ),
            esc_attr( $sdg['name'] ),
            esc_html( $sdg['icon'] ),
            $number
        );
    }

    /** Render multiple SDG badges from a comma-separated string. */
    public static function badges( string $goals_string ): string {
        $html = '';
        foreach ( self::parse_goals( $goals_string ) as $n ) {
            $html .= self::badge( $n );
        }
        return $html;
    }
}
