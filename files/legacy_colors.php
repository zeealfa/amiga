<?php
$LEGACY_BG_COLORS = [
    'white'       => '#ffffff',
    'red'         => '#ff2626',
    'whitesmoke'  => '#f4f4f4',
    'slateblue'   => '#637b94',
    'darkolive'   => '#575748',
    'lightgray'   => '#dddddd',
    'orange'      => '#ff9900',
    'gray'        => '#bbbbbb',
    'skyblue'     => '#6699cc',
    'darkred'     => '#c70000',
    'cyan'        => '#00ffff',
    'gold'        => '#f1c40f',
    'blue'        => '#006cd9',
    'purple'      => '#842dce',
    'teal'        => '#336666',
    'magenta'     => '#990099',
    'burntorange' => '#dc7633',
    'charcoal'    => '#333333',
    'green'       => '#229c22',
    'offwhite'    => '#fafafa',
    'pink'        => '#d61baf',
];

$LEGACY_TXT_COLORS = [
    'white'  => '#ffffff',
    'black'  => '#000000',
    'blue'   => '#006cd9',
    'red'    => '#ff2626',
    'green'  => '#229c22',
    'orange' => '#ff9900',
    'purple' => '#842dce',
];

function bg_hex(string $name): string
{
    global $LEGACY_BG_COLORS;
    return $LEGACY_BG_COLORS[$name] ?? '#000000';
}

function txt_hex(string $name): string
{
    global $LEGACY_TXT_COLORS;
    return $LEGACY_TXT_COLORS[$name] ?? '#000000';
}
