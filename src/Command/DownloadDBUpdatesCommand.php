<?php
/**
 *     @license
 *
 *     Copyright (C) 2020 debricked AB
 *
 *     This program is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Command for downloading updates of the database.
 *
 * @author Oscar Reimer <oscar.reimer@debricked.com>
 */
class DownloadDBUpdatesCommand extends Command
{

    use LockableTrait;

    protected static $defaultName = 'debricked:db:download';

    private const OPTION_API_URL = 'api_url';

    private string $dbUpdatesDir;

    private HttpClientInterface $httpClient;

    public function __construct(string $projectDir, HttpClientInterface $httpClient, string $name = null)
    {
        parent::__construct($name);

        $this->dbUpdatesDir = "$projectDir/dbupdates/";
        $this->httpClient = $httpClient;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(static::$defaultName)
            ->setDescription('This command fetches any available updates for the database from Debricked since last run.')
            ->addOption(self::OPTION_API_URL, null, InputOption::VALUE_OPTIONAL, 'API URL to communicate with', 'https://app.debricked.com/api');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock())
        {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $io = new SymfonyStyle($input, $output);
        $io->newLine(2);

        $timestampPath = "{$this->dbUpdatesDir}/timestamp.txt";

        $apiUrl = $input->getOption(self::OPTION_API_URL);
        $updatedAfter = null;

        if (\file_exists($timestampPath) === true)
        {
            $updatedAfter = \file_get_contents($timestampPath);
        }
        else
        {
            $updatedAfter = '1970-01-01T00:00:00+00:00';
        }

        $io->section("Downloading updates from {$updatedAfter} and later, using '{$apiUrl}' as API URL");

        try
        {
            $request = $this->httpClient->request
            (
                'GET',
                "{$apiUrl}/1.0/cves/by/id",
                [
                    'updatedAfter' => $updatedAfter,
                ]
            );
            $response = $request->getContent(true);
        }
        catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e)
        {
            $io->error("An error occurred when downloading DB updates: {$e->getMessage()}");

            return 1;
        }

        $decodedResponse = \json_decode($response, true);
        if ($decodedResponse === null)
        {
            $io->warning('Non-json response received from API. You might need to update this command.');

            return 0;
        }
        if (\count($decodedResponse) === 0)
        {
            $io->success('No further updates are available');

            return 0;
        }

        $lastCve = \end($decodedResponse);
        $lastUpdatedAt = $lastCve['updated_at'];
        \file_put_contents($timestampPath, $lastUpdatedAt);
        \file_put_contents("dbupdate-{$lastUpdatedAt}", $response);

        return 0;
    }

}
