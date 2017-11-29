<?php
namespace BretRZaun\DeployCommand\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use BretRZaun\DeploymentCommand\DeploymentCommand;
use BretRZaun\DeploymentCommand\ProcessFactory;
use Symfony\Component\Console\Tester\CommandTester;
use Laravel\Envoy\ParallelSSH;
use Symfony\Component\Process\Process;

class DeployCommandTest extends TestCase
{
    private function createApplication()
    {
        $application = new Application();
        $application->setCatchExceptions(false);
        $application->add(new DeploymentCommand(__DIR__.'/data'));
        return $application;
    }

    public function testRunWithoutEnvironment()
    {
        $application = $this->createApplication();

        $command = $application->find('deploy');
        $commandTester = new CommandTester($command);

        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "env").');
        $commandTester->execute(array(
            'command'  => $command->getName()
        ));
    }

    public function testRunWithMissingConfiguration()
    {
        $application = $this->createApplication();

        $command = $application->find('deploy');
        $commandTester = new CommandTester($command);

        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'env' => 'does-not-exist'
        ));
        $output = $commandTester->getDisplay();
        dump($output);
    }

    public function testRunEmptyConfiguration()
    {
        $application = $this->createApplication();
        $command = $application->find('deploy');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'env' => 'test_empty'
        ));
        $output = $commandTester->getDisplay();

        $this->assertContains('Deploy application (test_empty)', $output);
        $this->assertContains('[WARNING] No servers configured', $output);
        $this->assertContains('[OK] Deployment successful !', $output);
    }

    public function testRun()
    {
        $application = $this->createApplication();
        $command = $application->find('deploy');
        $mockSSH = $this->createMock(ParallelSSH::class);
        $mockFactory = $this->createMock(ProcessFactory::class);
        $mockProcess = $this->createMock(Process::class);

        $mockProcess->expects($this->any())
            ->method('run');
        $mockProcess->expects($this->any())
            ->method('isSuccessful')
            ->willReturn(true);

        $mockFactory->expects($this->exactly(3))
                ->method('factory')
                ->withConsecutive(
                  ['command1'],
                  ['rsync -avz --delete  . user@my-server:/target-folder'],
                  ['command2']
                )
                ->willReturn($mockProcess);

        $mockSSH->expects($this->any())
            ->method('run');

        $command->setProcessFactory($mockFactory);
        $command->setRemoteProcessor($mockSSH);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command'  => $command->getName(),
                'env' => 'test'
            ),
            array(
                'vvv' => true
            )
          );
        $output = $commandTester->getDisplay();

        #dump($output);
        $this->assertContains('Deploy application (test)', $output);
        $this->assertContains('- Run local script(s) (pre-deploy-cmd)', $output);
        $this->assertContains('- Run remote scripts (pre-deploy-cmd)', $output);
        $this->assertContains('- Transfer files', $output);
        $this->assertContains('- Run remote scripts (post-deploy-cmd)', $output);
        $this->assertContains('- Run local script(s) (post-deploy-cmd)', $output);
        $this->assertContains('[OK] Deployment successful !', $output);
    }
}
