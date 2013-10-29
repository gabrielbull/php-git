<?php

namespace Git;
use Git;

class Command
{
    /**
     * Execute a command
     *
     * @param Git $repo
     * @param string $command
     * @param bool $check
     * @param bool $quiet
     * @param bool $worktreeEnabled
     * @return string
     */
    public static function run(Git $repo, $command, $check = true, $quiet = false, $worktreeEnabled = true)
    {
        $dir = $repo->getDir();
        $dir = preg_replace('!/{2,}!', '/', $dir);
        $dir = rtrim($dir, '/');

        $workTree = $dir;

        $dir = $dir . "/.git";

        if (!$check || is_dir($dir)) {
            if ($worktreeEnabled) {
                $command = preg_replace("/^git/", "git --work-tree={$workTree}", $command);
            }

            $command = preg_replace("/^git/", "git --git-dir={$dir}", $command);

            if ($quiet) {
                $command .= ' --quiet';
            }

            $retval = '';

            ob_start();

            $handle = popen($command, 'r');

            while(!feof($handle)) {
                $read = fgets ($handle);
                echo $read;
            }

            pclose($handle);

            $retval .= ob_get_contents();
            ob_end_clean();

            return $retval;
        }

        return false;
    }

}