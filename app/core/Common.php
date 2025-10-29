<?php

namespace Core;
/**
* Common function class
*
*/

class Common {

    private static $instance = null;

    // Private constructor to prevent direct instantiation
    private function __construct() {}

    // Get the singleton instance
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function removePrefixFromKeys( array $data ): array {
        // Loop through the array
        foreach ( $data as &$item ) {
            // Create a new array to store renamed keys
            $newItem = [];
            foreach ( $item as $key => $value ) {
                // Check if the key contains an underscore
                if ( strpos( $key, '_' ) !== false ) {
                    // Split the key by the first underscore
                    $parts = explode( '_', $key, 2 );
                    // If there are two parts and the second part is not empty
                    if ( count( $parts ) === 2 && !empty( $parts[ 1 ] ) ) {
                        // Use the second part as the new key
                        $newKey = $parts[ 1 ];
                        $newItem[ $newKey ] = $value;
                    } else {
                        // Keep the key unchanged if it doesn't fit the pattern
                        $newItem[ $key ] = $value;
                    }
                } else {
                    // Keep keys without underscores unchanged
                    $newItem[ $key ] = $value;
                }
            }
            // Replace the item with the new array
            $item = $newItem;
        }

        // Unset the reference to avoid unexpected behavior
        unset( $item );

        return $data;
    }

    public function removeFirstPrefix(array $array): array {
        $result = [];

        foreach ($array as $key => $value) {
            // Find first underscore and remove everything before it (including the underscore)
            $pos = strpos($key, '_');
            if ($pos !== false) {
                $newKey = substr($key, $pos + 1);
            } else {
                $newKey = $key; // If no underscore, keep original
            }

            $result[$newKey] = $value;
        }

        return $result;
    }

}