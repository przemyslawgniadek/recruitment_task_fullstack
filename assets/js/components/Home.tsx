import React, { Suspense, lazy } from 'react';
import { Route, Redirect, Switch, Link } from 'react-router-dom';

// Lazy load components for better performance
const SetupCheck = lazy(() => import('./SetupCheck'));
const Dashboard = lazy(() => import('./Dashboard'));
const HistoryView = lazy(() => import('./HistoryView'));

/**
 * Home Component - Main application layout
 * 
 * Provides navigation and routing for the FX Desk application.
 * Currently includes setup check, will be extended with dashboard and history views.
 */
const Home: React.FC = () => {
  return (
    <div>
      <nav className="navbar navbar-expand-lg navbar-dark bg-dark">
        <Link className="navbar-brand" to="#">
          Telemedi FX Desk
        </Link>
        <div id="navbarText">
                 <ul className="navbar-nav mr-auto">
                   <li className="nav-item">
                     <Link className="nav-link" to="/dashboard">
                       💱 Dashboard
                     </Link>
                   </li>
                   <li className="nav-item">
                     <Link className="nav-link" to="/history">
                       📈 History
                     </Link>
                   </li>
                   <li className="nav-item">
                     <Link className="nav-link" to="/setup-check">
                       🔧 Setup Check
                     </Link>
                   </li>
                 </ul>
        </div>
      </nav>
      
      <Suspense fallback={
        <div className="d-flex justify-content-center align-items-center" style={{height: '50vh'}}>
          <div className="text-center">
            <div className="spinner-border text-primary mb-3" style={{width: '3rem', height: '3rem'}} />
            <h4>Loading...</h4>
            <p className="text-muted">Please wait while we load the component</p>
          </div>
        </div>
      }>
        <Switch>
          <Redirect exact from="/" to="/dashboard" />
          <Route path="/dashboard" render={() => {
            console.log("🚀 Dashboard route matched!");
            return <Dashboard />;
          }} />
          <Route path="/history/:currency?" render={() => {
            console.log("📈 History route matched!");
            return <HistoryView />;
          }} />
          <Route path="/setup-check" component={SetupCheck} />
        </Switch>
      </Suspense>
    </div>
  );
};

export default Home;
