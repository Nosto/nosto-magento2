#!/usr/bin/env groovy

node {
    stage "Prepare environment"
        checkout scm
        def environment  = docker.build 'platforms-base'

        environment.inside {
            stage "Update Dependencies"
                withCredentials([usernamePassword(credentialsId: 'magento', usernameVariable: 'USERNAME', passwordVariable: 'PASSWORD')]) {
                  sh "composer config http-basic.repo.magento.com $USERNAME $PASSWORD"
                }
                sh "composer install || true"

            stage "Code Sniffer"
                catchError {
                    sh "./vendor/bin/phpcbf --standard=ruleset.xml || true"
                    sh "./vendor/bin/phpcs --standard=ruleset.xml --report=checkstyle --report-file=phpcs.xml || true"
                }
                step([$class: 'hudson.plugins.checkstyle.CheckStylePublisher', pattern: 'phpcs.xml', unstableTotalAll:'0'])

            stage "Copy-Paste Detection"
                catchError {
                    sh "./vendor/bin/phpcpd --exclude=vendor --exclude=var --exclude=build --log-pmd=phpcpd.xml . || true"
                }

            stage "Mess Detection"
                catchError {
                    sh "./vendor/bin/phpmd . xml codesize,naming,unusedcode,controversial,design --exclude vendor,var,build,tests --reportfile phpmd.xml || true"
                }
                //step([$class: 'PmdPublisher', pattern: 'phpmd.xml', unstableTotalAll:'0'])

            stage "Phan Analysis"
                catchError {
                    sh "./vendor/bin/phan --config-file=phan.php --output-mode=checkstyle --output=phan.xml || true"
                }
                step([$class: 'hudson.plugins.checkstyle.CheckStylePublisher', pattern: 'phan.xml', unstableTotalAll:'0'])

            stage "Package"
                version = sh(returnStdout: true, script: 'git rev-parse --short HEAD').trim()
                sh "composer archive --format=zip --file=${version}"
                sh "composer validate-archive -- ${version}.zip"
                archiveArtifacts "${version}.zip"
        }

    stage "Cleanup"
        deleteDir()
}
