<?php
/**
 * Authenticate to Pantheon and store a local secret token.
 *
 */
class Auth_Command extends Terminus_Command {

  /**
   * Log in as a user
   *
   *  ## OPTIONS
   * [<email>]
   * : Email address to log in as.
   *
   * [--password=<value>]
   * : Log in non-interactively with this password. Useful for automation.
   */
  public function login( $args, $assoc_args ) {
      if ( empty( $args ) ) {
        $email = Terminus::prompt( "Your email address?", NULL );
      }
      else {
        $email = $args[0];
      }

      if ( \Terminus\Utils\is_valid_email( $email ) ) {
        if ( !isset( $assoc_args['password'] ) ) {
          exec("stty -echo");
          $password = Terminus::prompt( "Your dashboard password (input will not be shown)" );
          exec("stty echo");
          Terminus::line();
        }
        else {
          $password = $assoc_args['password'];
        }
        Terminus::line( "Logging in as $email" );
        $data = \Terminus\Login\auth( $email, $password );
        if ( $data != FALSE ) {
          Terminus::line( "Success!" );
          $this->cache->put_data('session', $data);
        }
        else {
          Terminus::line( "Login Failed/" );
        }
      }
      else {
        Terminus::line( "Error: invalid email address" );
      }
  }

  /**
   * Log yourself out and remove the secret session key.
   */
  public function logout() {
    $this->line( "Logging out of to Pantheon." );
    $this->cache->remove('session');
  }

  /**
   * Find out what user you are logged in as.
   */
  public function whoami() {
    if ($this->session) {
      $this->line( "You are authenticated as ". $this->session->email );
    }
    else {
      $this->line( "You are not logged in." );
    }
  }

}

Terminus::add_command( 'auth', 'Auth_Command' );

