#!/usr/bin/env groovy

pipeline {

  environment {
    withCredentials([usernamePassword(credentialsId: 'amazon', usernameVariable: 'USERNAME', passwordVariable: 'PASSWORD')]) {
      FOO = "foo"
    }
  }

  agent {
    dockerfile {
      additionalBuildArgs '--build-arg REPOUSER=foo --build-arg REPOPASS=bar'
    }
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
      }
    }
    stage('Prebuild') {
      steps {
        script {
          sh 'echo hi'
        }
      }
    }
  }
}
