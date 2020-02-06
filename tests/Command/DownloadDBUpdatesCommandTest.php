<?php
/**
 * @license
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

namespace App\Tests\Command;

use App\Command\DownloadDBUpdatesCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests @see DownloadDBUpdatesCommand.
 *
 * @author Oscar Reimer <oscar.reimer@debricked.com>
 */
class DownloadDBUpdatesCommandTest extends KernelTestCase
{

    public function setUp()
    {
        parent::setUp();

        $this::bootKernel();

        // Delete timestamp.txt so we are "clean" between tests
        \unlink(
            $this::$container->getParameter(
                'kernel.project_dir'
            ).DownloadDBUpdatesCommand::DB_UPDATES_SUB_DIRECTORY.'timestamp.txt'
        );
    }

    public function testExecute()
    {
        $apiUrl = null;
        if (\array_key_exists('DEBRICKED_API_URL', $_ENV)) {
            $apiUrl = $_ENV['DEBRICKED_API_URL'];
        }
        $kernel = static::createKernel();
        $application = new Application($kernel);

        // Set up command
        $command = $application->find(DownloadDBUpdatesCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $input = [
            DownloadDBUpdatesCommand::ARGUMENT_USERNAME => $_ENV['DEBRICKED_USERNAME'],
            DownloadDBUpdatesCommand::ARGUMENT_PASSWORD => $_ENV['DEBRICKED_PASSWORD'],
        ];
        if (empty($apiUrl) === false) {
            $input['--'.DownloadDBUpdatesCommand::OPTION_API_URL] = $apiUrl;
        }

        // Run a first time, should be from beginning
        $commandTester->execute($input);
        $outputFirstRun = $commandTester->getDisplay();
        $this->assertEquals(0, $commandTester->getStatusCode(), $outputFirstRun);
        $this->assertContains(
            'Downloading updates from '.DownloadDBUpdatesCommand::DEFAULT_START_DATE,
            $outputFirstRun
        );
        $this->assertContains('Successfully downloaded updates. Update file is:', $outputFirstRun);

        // Run a second time, now we should be downloading updates from a more advanced date
        $commandTester->execute($input);
        $outputSecondRun = $commandTester->getDisplay();
        $this->assertEquals(0, $commandTester->getStatusCode(), $outputSecondRun);
        $this->assertRegExp('/Downloading updates from 2\d{3}/', $outputSecondRun);
        $this->assertNotContains(
            'Downloading updates from '.DownloadDBUpdatesCommand::DEFAULT_START_DATE,
            $outputSecondRun
        );
        $this->assertContains('Successfully downloaded updates. Update file is:', $outputSecondRun);
    }

}
