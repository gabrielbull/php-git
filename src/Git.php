<?php

use Git\Command;

/**
 * Git class
 *
 * @package git
 */
class Git
{
    private static $remoteUrlChecks = array();

    private $dir;

    /**
     * Init repo
     */
    public function __construct()
    {

    }

    /**
     * @param string $dir
     * @return self
     */
    public function setDir($dir)
    {
        $this->dir = (string)$dir;
        return $this;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * Get remotes repositories
     *
     * @return array
     */
    public function getRemotes()
    {
        $retval = Command::run($this, "git remote -v");
        $remotes = array();
        foreach(explode(PHP_EOL, $retval) as $line) {
            preg_match("/([^\\s]*)(.*) (\([a-z]*\))/i", $line, $match);
            if (!empty($match[1]) && !empty($match[2])) {
                $remotes[$match[1]] = trim($match[2]);
            }
        }

        return $remotes;
    }

    /**
     * Check if remote url is a valid repository
     *
     * @param string $url
     * @return bool
     */
    private function checkRemoteUrl($url)
    {
        if (isset(self::$remoteUrlChecks[$url])) {
            return self::$remoteUrlChecks[$url];
        }

        $retval = false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

        if (curl_exec($ch) === true) {
            $retval = true;
        }

        curl_close($ch);

        self::$remoteUrlChecks[$url] = $retval;
        return $retval;
    }

    /**
     * Get remote branches
     *
     * @return array
     */
    public function getRemoteBranches()
    {
        $retval = Command::run($this, 'git branch -r');
        $retval = preg_replace('/\s{2,}/', ' ', trim($retval));

        $branches = array();

        foreach(explode(' ', $retval) as $branch) {
            if (!empty($branch)) {
                $branches[] = $branch;
            }
        }

        return $branches;
    }

    /**
     * Get local branches
     *
     * @return array
     */
    public function getBranches()
    {
        $retval = Command::run($this, 'git branch');
        $retval = preg_replace('/\s{2,}/', ' ', trim($retval));
        return explode(' ', $retval);
    }

    /**
     * Get local head branch name
     *
     * @return string
     */
    public function getHeadBranch()
    {
        $retval = Command::run($this, 'git branch');
        $branches = explode(PHP_EOL, $retval);
        foreach($branches as $branch) {
            if (strpos($branch, '*') === 0) {
                return trim($branch, '* ');
            }
        }
        return null;
    }

    /**
     * Get all commits for a branch
     *
     * @param string $head
     * @param int $count
     * @return array
     */
    public function getCommits($head = null, $count = null)
    {
        $command = "git log";

        // TODO: replace by script below once it passes tests
        if (null !== $head) {
            $command .= " {$head}";
        }
        // TODO: this does not pass tests
        /*if (null !== $head && null === $count) {
            $command .= " {$head}";
        }

        if (null !== $count && null === $head) {
            $command .= " HEAD~{$count}..HEAD";
        }

        if (null !== $count && null !== $head) {
            $command .= " {$head}~{$count}..{$head}";
        }*/

        $retval = Command::run($this, $command);
        $commits = preg_split("/(commit [a-zA-Z0-9]*)/", $retval, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);

        $output = array();
        foreach($commits as $key => $commit) {
            if ($key%2 === 0) {
                $commitHash = preg_replace('/^commit ([a-zA-Z0-9]*)$/', '$1', $commit);
            } else if (isset($commitHash)) {
                // Match author
                preg_match("/Author: (.*)/i", $commit, $matches);
                if (isset($matches[1])) {
                    $author = trim($matches[1]);
                } else {
                    $author = '';
                }

                // Match date
                preg_match("/Date: (.*)/i", $commit, $matches);
                if (isset($matches[1])) {
                    $date = date('Y-m-d H:i:s', strtotime(trim($matches[1])));
                } else {
                    $date = '';
                }

                // Get message
                $lines = preg_split('/Date: (.*)'.PHP_EOL.'/', $commit);
                $message = trim(end($lines));

                $output[] = array(
                    'commit' => $commitHash,
                    'author' => $author,
                    'date' => $date,
                    'message' => $message
                );

                unset($commitHash);
            }
        }

        return $output;
    }

    /**
     * Fetch all remote branches
     *
     * @return bool
     */
    public function fetchAll()
    {
        $fetched = false;
        $remotes = self::getRemotes();
        foreach($remotes as $remote => $url) {
            if (self::checkRemoteUrl($url)) {
                // Fetch remote
                Command::run($this, "git fetch {$remote}");
                $fetched = true;
            }
        }
        return $fetched;
    }

    /**
     * Check if local branch is tracking remote branch
     *
     * @param string $branch
     * @return bool
     */
    public function isTracking($branch)
    {
        $remotes = self::getRemotes();
        if (isset($remotes['origin']) && self::checkRemoteUrl($remotes['origin'])) {

            $retval = Command::run($this, 'git remote show origin');

            if (
                preg_match("/ merges with remote {$branch}/i", $retval) &&
                preg_match("/ pushes to {$branch}/i", $retval)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add remote repo to branch
     *
     * @param string $remote
     * @return string
     */
    public function addRemote($remote)
    {
        return Command::run($this, 'git remote add origin ' . $remote);
    }

    /**
     * Add remote repo to branch
     *
     * @param string $branch
     * @return string
     */
    public function checkoutRemote($branch)
    {
        return Command::run($this, 'git checkout --track ' . $branch);
    }

    /**
     * Checkout a branch
     *
     * @param string $branch
     * @return string
     */
    public function checkout($branch)
    {
        return Command::run($this, 'git checkout ' . $branch . ' -q', true);
    }

    /**
     * Checkout a branch at specific commit
     *
     * @param string $branch
     * @param string $commit
     * @return string
     */
    public function checkoutCommit($branch, $commit)
    {
        return Command::run($this, 'git checkout -b ' . $branch . ' ' . $commit . ' -q', true);
    }

    /**
     * Delete a local branch
     *
     * @param string $branch
     * @return string
     */
    public function removeBranch($branch)
    {
        return Command::run($this, 'git branch -D ' . $branch);
    }

    /**
     * Rename a local branch
     *
     * @param string $branch
     * @param string $name
     * @return string
     */
    public function renameBranch($branch, $name)
    {
        return Command::run($this, 'git branch -m ' . $branch . ' ' . $name);
    }

    /**
     * Initialize git repo
     *
     * @return bool
     */
    public function init()
    {
        $retval = Command::run($this, 'git init', false);
        if (stristr($retval, 'Initialized') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Initialize bare git repo
     *
     * @return  bool
     */
    public function initBare()
    {
        $retval = Command::run($this, 'git init --bare');
        if (stristr($retval, 'Initialized') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Commit.
     *
     * @param string $msg
     * @param string $name
     * @param string $email
     * @return string
     */
    public function commit($msg, $name = null, $email = null)
    {
        $msg = preg_replace('/[^\s\w\-:]/', '', $msg);

        Command::run($this , 'git add -A');

        if (null !== $email) {
            Command::run($this, 'git config user.email '.escapeshellarg($email));
        }
        if (null !== $name) {
            Command::run($this, 'git config user.name '.escapeshellarg($name));
        }

        $command = "git commit -m " . escapeshellarg($msg);

        $author = "$name <$email>";
        $command .= " --author=". escapeshellarg($author);

        return Command::run($this, $command);

    }

    /**
     * Pull
     *
     * @param string $remote
     * @param string $branch
     * @return string
     */
    public function pull($remote = null, $branch = null)
    {
        $command = "git pull";

        if (null !== $remote) {
            $command .= " {$remote}";
        }

        if (null !== $remote && null !== $branch) {
            $command .= " {$branch}";
        }

        return Command::run($this, $command, true, true);
    }

    /**
     * Push
     *
     * @param string $remote
     * @param string $branch
     * @return string
     */
    public function push($remote = null, $branch = null)
    {
        $command = "git push";

        if (null !== $branch) {
            $command .= " {$remote}";
        }

        if (null !== $remote && null !== $branch) {
            $command .= " {$branch}";
        }

        return Command::run($this, $command);
    }

    /**
     * Status
     *
     * @return string
     */
    public function status()
    {
        return Command::run($this, "git status");
    }

    /**
     * Clear all changed files
     *
     * @return string
     */
    public function clean()
    {
        return Command::run($this, "git checkout -- .");
    }
}