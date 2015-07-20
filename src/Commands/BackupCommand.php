<?php
/*
 * This file is part of the PHPConsole package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*/
namespace Longman\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package     PHPConsole
 * @author       Avtandil Kikabidze <akalongman@gmail.com>
 * @copyright   2015 Avtandil Kikabidze <akalongman@gmail.com>
 * @license       http://opensource.org/licenses/mit-license.php  The MIT License (MIT)
 * @link            http://www.github.com/akalongman/php-console
*/
class BackupCommand extends Command
{
    protected $name = 'longman:backup';
    protected $date;
    protected $output;

    protected $bpath;
    protected $project;
    protected $mode;
    protected $modes = array('full', 'sql', 'files');

    protected $dbhost;
    protected $dbuser;
    protected $dbpass;
    protected $dbname;
    protected $dbport;
    protected $exclude_tables;
    protected $include_tables;

    protected function configure()
    {

        $this->setName($this->name)
             ->setDescription('Create backup of project')
             ->setDefinition(array(
                //new InputOption('bpath', 'b', InputOption::VALUE_OPTIONAL, 'Paths for backups', getcwd()),
                new InputOption(
                    'bpath',
                    'b',
                    InputOption::VALUE_OPTIONAL,
                    'Path for backups',
                    '/media/D/backups'
                ),
                new InputOption(
                    'project',
                    'p',
                    InputOption::VALUE_REQUIRED,
                    'Project name'
                ),
                new InputOption(
                    'mode',
                    'm',
                    InputOption::VALUE_OPTIONAL,
                    'Backup mode',
                    'full'
                ), // full, sql, files
                new InputOption(
                    'dbhost',
                    'dbhost',
                    InputOption::VALUE_OPTIONAL,
                    'Database host',
                    'localhost'
                ),
                new InputOption(
                    'dbuser',
                    'dbuser',
                    InputOption::VALUE_OPTIONAL,
                    'Database user'
                ),
                new InputOption(
                    'dbpass',
                    'dbpass',
                    InputOption::VALUE_OPTIONAL,
                    'Database password'
                ),
                new InputOption(
                    'dbname',
                    'dbname',
                    InputOption::VALUE_OPTIONAL,
                    'Database name'
                ),
                new InputOption(
                    'dbport',
                    'dbport',
                    InputOption::VALUE_OPTIONAL,
                    'Database port',
                    3306
                ),
                new InputOption(
                    'exclude-tables',
                    'exclude-tables',
                    InputOption::VALUE_OPTIONAL,
                    'Comma separated tables list for exclude'
                ),
                new InputOption(
                    'include-tables',
                    'include-tables',
                    InputOption::VALUE_OPTIONAL,
                    'Comma separated tables list for include'
                ),
             ))
            ->setHelp(<<<EOT
Generate backup of project

Usage:

<info>$this->name <env></info>

EOT
            );
        $this->date = date('Y-m-d');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$header_style = new OutputFormatterStyle('yellow', 'black', array('bold'));
        //$output->getFormatter()->setStyle('header', $header_style);
        $this->output = $output;

        $bpath = $input->getOption('bpath');
        if (!is_dir($bpath)) {
            throw new \InvalidArgumentException('Path ' . $bpath . ' not found!');
        }
        $this->bpath = $bpath;

        $project = $input->getOption('project');
        if (empty($project)) {
            throw new \InvalidArgumentException('Project name not specified');
        }
        $this->project = $project;

        $mode = $input->getOption('mode');
        if (!in_array($mode, $this->modes)) {
            throw new \InvalidArgumentException('Backup mode not specified');
        }
        $this->mode = $mode;

        $this->dbhost = $input->getOption('dbhost');
        $this->dbuser = $input->getOption('dbuser');
        $this->dbpass = $input->getOption('dbpass');
        $this->dbname = $input->getOption('dbname');
        $this->dbport = $input->getOption('dbport');

        $exclude_tables = $input->getOption('exclude-tables');
        $this->exclude_tables = !empty($exclude_tables) ? explode(',', $input->getOption('exclude-tables')) : array();

        $include_tables = $input->getOption('include-tables');
        $this->include_tables = !empty($include_tables) ? explode(',', $input->getOption('include-tables')) : array();


        if (!$this->checkProjectFolder()) {
            throw new \LogicException('Project folder can not created');
        }

        $this->backupFTP();


        switch ($this->mode) {
            default:
            case 'sql':
                $this->backupSQL();
                break;

        }

    }


    protected function backupSQL()
    {
        $folder_path = $this->bpath . '/' . $this->project . '/' . $this->date;
        $file_path = 'backup_' . $this->mode . '_' . date('His');
        $sql_ext = '.sql';

        $sql_path = $folder_path . '/' . $file_path . $sql_ext;
        $tar_path = $folder_path . '/' . $file_path . '.tar.gz';


        if (empty($this->dbhost)) {
            throw new \InvalidArgumentException('Database host not specified!');
        }

        if (empty($this->dbuser)) {
            throw new \InvalidArgumentException('Database user not specified!');
        }

        if (empty($this->dbpass)) {
            throw new \InvalidArgumentException('Database password not specified!');
        }

        if (empty($this->dbname)) {
            throw new \InvalidArgumentException('Database name not specified!');
        }

        if (empty($this->dbport)) {
            throw new \InvalidArgumentException('Database port not specified!');
        }
        $cmd = 'mysql -NBA -h ' . $this->dbhost . ' -u ' . $this->dbuser . ' -p' . $this->dbpass . ' \\
-D ' . $this->dbname . ' -e "SHOW TABLES"';

        list($tables, $return) = $this->exec($cmd);


        if ($return) {
            throw new \RuntimeException('Can not get tables list');
        }

        if (empty($tables)) {
            throw new \RuntimeException('No tables in this database');
        }


        foreach ($tables as $table) {
            if (!empty($this->exclude_tables) && in_array($table, $this->exclude_tables)) {
                $this->output->write('<comment>Excluded table `'.$table.'`</comment>', true);
                continue;
            }

            if (!empty($this->include_tables) && !in_array($table, $this->include_tables)) {
                continue;
            }


            $cmd = 'echo "--\n\\
-- Dumping table \`'.$this->dbname.'_'.$table.'\`\n\\
--\n" >> '.$sql_path;

            $this->exec($cmd);

            $cmd = 'mysql -NBA -h ' . $this->dbhost . ' -u ' . $this->dbuser . ' -p' . $this->dbpass . ' -e \\
"SELECT \`table_name\`, \`data_length\` AS \`size\` \\
FROM \`information_schema\`.\`TABLES\` \\
WHERE \`table_schema\` = \"' . $this->dbname . '\" AND \`table_name\`=\"'.$table.'\" \\
LIMIT 1"';


            list($output, $return) = $this->exec($cmd);

            $size = 0;
            if (!$return && !empty($output)) {
                $ex = explode("\t", $output[0]);
                $size = !empty($ex[1]) ? trim($ex[1]) : 0;
            }

            $string = $size ?
                'Dumping table `'.$table.'` ('.$this->formatBytes($size, 2).'):' :
                'Dumping table `'.$table.'`:';

            $this->output->write('<comment>'.$string.'</comment>', true);


            $cmd = 'mysqldump --compact --port=' . $this->dbport . ' \\
--host=' . $this->dbhost . ' \\
--user=' . $this->dbuser . ' \\
--password=' . $this->dbpass . ' ' . $this->dbname . ' ' . $table . ' \\
| pv -s '.$size.' >> ' . $sql_path . '';

            list($output, $return) = $this->exec($cmd);
            if ($return) {
                $this->output->write('<error>Error</error>', true);
                continue;
            }


            list($output, $return) = $this->exec('echo "\n" >> '.$sql_path);

            $this->output->write('<info>Success</info>', true);
            $this->output->write('', true);
        }

        if (!file_exists($sql_path)) {
            $this->output->write('<error>Backup file not created created: '.$sql_path.'</error>', true);
            return false;
        }



        $this->output->write('<comment>Compressing sql dump file</comment>', true);
        $cmd = 'cd '.$folder_path.' && tar -cf - '.$file_path . $sql_ext.' \\
| pv -s $(wc -c '.$file_path . $sql_ext.' | awk \'{print $1}\') | gzip -9 > '.$tar_path;

        list($output, $return) = $this->exec($cmd);
        if ($return) {
            throw new \RuntimeException('Can not create archive from ' . $sql_path . "\n" . implode("\n", $output));
        }

        $this->output->write('', true);
        $this->output->write('<info>Backup successfully created: '.$tar_path.'</info>', true);


        $cmd = 'rm -f '.$sql_path;
        list($output, $return) = $this->exec($cmd);

    }

    protected function backupFTP()
    {
        $ftphost = '';
        $ftpuser = '';
        $ftppass = '';
        $ftptargetfolder = '';
        $ftpdestfolder = $this->bpath . '/' . $this->project . '/' . $this->date;

        $cut_dirs = count(explode('/', $ftptargetfolder));
        if ($cut_dirs > 1) {
            $cut_dirs -= 2;
        }

        $this->output->write('<comment>Download files from server</comment>', true);

        $cmd = 'cd '.$ftpdestfolder.' && wget --recursive --no-verbose --no-parent \\
--preserve-permissions --mirror - \\
--tries=5 --timeout=30 --local-encoding=utf-8 --remote-encoding=utf-8 \\
--user="'.$ftpuser.'" --password="'.$ftppass.'" ftp://'.$ftphost.''.$ftptargetfolder.' ';


        list($output, $return) = $this->exec($cmd);
  var_dump($return);
  var_dump($output);
  die;



    }


    protected function checkProjectFolder()
    {
        $path = $this->bpath . '/' . $this->project . '/' . $this->date;
        if (!is_dir($path)) {
            $this->output->write('<comment>Creating folder "' . $path . '": </comment>', false);

            $status = mkdir($path, 0777, true);
            if (!$status) {
                $this->output->write('<error>Error</error>', true);
                return false;
            } else {
                $this->output->write('<info>Success</info>', true);
            }
        }
        return true;
    }

    protected function exec($cmd)
    {
        $output = array();
        $return = 0;

        exec($cmd, $output, $return);

        return array($output, $return);
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
