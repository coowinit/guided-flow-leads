<?php
class GFL_Flow {

    public static function get_next_step($step, $option = null, $steps = []) {

        // 1. option next
        if ($option && !empty($option['next'])) {
            return $option['next'];
        }

        // 2. step default next
        if (!empty($step['next'])) {
            return $step['next'];
        }

        // 3. fallback（按顺序）
        $keys = array_keys($steps);
        $index = array_search($step['id'], $keys);

        if ($index !== false && isset($keys[$index + 1])) {
            return $keys[$index + 1];
        }

        return null;
    }
}