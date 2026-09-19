<?php
/**
 * Local album media/sleeve condition and notes (not synced with Discogs).
 */
class AlbumPersonalFields {
  const NOTES_MAX_LENGTH = 2000;

  /**
   * Discogs grade strings stored exactly as shown in the UI.
   *
   * @var string[]
   */
  public static $ALLOWED_GRADES = [
    'Mint (M)',
    'Near Mint (NM or M-)',
    'Very Good Plus (VG+)',
    'Very Good (VG)',
    'Good Plus (G+)',
    'Good (G)',
    'Fair (F)',
    'Poor (P)',
  ];

  /**
   * Normalize and validate personal fields from an API/input array.
   *
   * @param array $input
   * @return array{ok:bool,error:?string,media_condition:string,sleeve_condition:string,notes:string}
   */
  public static function normalizeFromInput($input) {
    $media = isset($input['media_condition']) ? trim((string) $input['media_condition']) : '';
    $sleeve = isset($input['sleeve_condition']) ? trim((string) $input['sleeve_condition']) : '';
    $notes = isset($input['notes']) ? trim((string) $input['notes']) : '';

    if ($media !== '' && !in_array($media, self::$ALLOWED_GRADES, true)) {
      return [
        'ok' => false,
        'error' => 'Invalid media condition',
        'media_condition' => '',
        'sleeve_condition' => '',
        'notes' => '',
      ];
    }

    if ($sleeve !== '' && !in_array($sleeve, self::$ALLOWED_GRADES, true)) {
      return [
        'ok' => false,
        'error' => 'Invalid sleeve condition',
        'media_condition' => '',
        'sleeve_condition' => '',
        'notes' => '',
      ];
    }

    if (strlen($notes) > self::NOTES_MAX_LENGTH) {
      return [
        'ok' => false,
        'error' => 'Notes must be at most ' . self::NOTES_MAX_LENGTH . ' characters',
        'media_condition' => '',
        'sleeve_condition' => '',
        'notes' => '',
      ];
    }

    return [
      'ok' => true,
      'error' => null,
      'media_condition' => $media,
      'sleeve_condition' => $sleeve,
      'notes' => $notes,
    ];
  }

  /**
   * Short label for table display.
   *
   * @param string $grade
   * @return string
   */
  public static function shortLabel($grade) {
    $grade = trim((string) $grade);
    $map = [
      'Mint (M)' => 'M',
      'Near Mint (NM or M-)' => 'NM',
      'Very Good Plus (VG+)' => 'VG+',
      'Very Good (VG)' => 'VG',
      'Good Plus (G+)' => 'G+',
      'Good (G)' => 'G',
      'Fair (F)' => 'F',
      'Poor (P)' => 'P',
    ];

    return isset($map[$grade]) ? $map[$grade] : '';
  }

  /**
   * Compact media/sleeve cell text (e.g. "NM / VG+").
   *
   * @param string|null $media
   * @param string|null $sleeve
   * @return string
   */
  public static function formatTableCell($media, $sleeve) {
    $m = self::shortLabel($media);
    $s = self::shortLabel($sleeve);

    if ($m === '' && $s === '') {
      return '';
    }
    if ($m === '') {
      return '— / ' . $s;
    }
    if ($s === '') {
      return $m . ' / —';
    }

    return $m . ' / ' . $s;
  }
}
