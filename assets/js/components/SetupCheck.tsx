import React, { useState, useEffect } from 'react';
import axios from 'axios';

/**
 * Setup Check Component
 * 
 * Tests API connectivity and basic functionality.
 * Verifies that backend API is responding correctly.
 */

interface SetupCheckState {
  setupCheck: boolean | null;
  loading: boolean;
  error?: string;
}

const SetupCheck: React.FC = () => {
  const [state, setState] = useState<SetupCheckState>({
    setupCheck: null,
    loading: true
  });

  const getBaseUrl = (): string => {
    // Use current origin for production, localhost:8000 for development
    return window.location.origin;
  };

  const checkApiSetup = async (): Promise<void> => {
    try {
      const baseUrl = getBaseUrl();
      const response = await axios.get(`${baseUrl}/api/setup-check?testParam=1`);
      
      const responseIsOK = response.data && response.data.testParam === 1;
      setState({
        setupCheck: responseIsOK,
        loading: false
      });
    } catch (error) {
      console.error('API Setup Check failed:', error);
      setState({
        setupCheck: false,
        loading: false,
        error: error instanceof Error ? error.message : 'Unknown error'
      });
    }
  };

  useEffect(() => {
    checkApiSetup();
  }, []);

  const { loading, setupCheck, error } = state;

  return (
    <div>
      <section className="row-section">
        <div className="container">
          <div className="row mt-5">
            <div className="col-md-8 offset-md-2">
              <h2 className="text-center">
                <span>FX Desk Setup Check</span> @ Telemedi
              </h2>

              {loading ? (
                <div className="text-center">
                  <div className="spinner-border text-primary" role="status">
                    <span className="sr-only">Loading...</span>
                  </div>
                  <p className="mt-3">Checking API connectivity...</p>
                </div>
              ) : (
                <div className="text-center">
                  {setupCheck === true ? (
                    <div>
                      <h3 className="text-success">
                        <strong>✅ API Connection Successful!</strong>
                      </h3>
                      <p className="text-muted">
                        Backend API is responding correctly. Ready for FX Desk operations.
                      </p>
                    </div>
                  ) : (
                    <div>
                      <h3 className="text-danger">
                        <strong>❌ API Connection Failed</strong>
                      </h3>
                      <p className="text-muted">
                        Unable to connect to backend API.
                        {error && (
                          <><br />Error: {error}</>
                        )}
                      </p>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </section>
    </div>
  );
};

export default SetupCheck;
