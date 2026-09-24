import { Component } from 'react';

/**
 * Catches rendering errors so one broken part never blanks the whole page.
 * Resets when `resetKey` changes (e.g. on navigation).
 */
export default class ErrorBoundary extends Component {
    /** @param {{children: import('react').ReactNode, resetKey?: string, fallback?: import('react').ReactNode}} props */
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidUpdate(previousProps) {
        if (this.state.hasError && previousProps.resetKey !== this.props.resetKey) {
            this.setState({ hasError: false });
        }
    }

    componentDidCatch(error) {
        if (import.meta.env.DEV) {
            console.error(error);
        }
    }

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        return (
            this.props.fallback ?? (
                <div className="container py-5" role="alert">
                    <h1 className="h3" tabIndex={-1}>
                        Something went wrong
                    </h1>
                    <p>This part of the page could not be displayed. Please try again.</p>
                    <button type="button" className="btn btn-primary" onClick={() => this.setState({ hasError: false })}>
                        Try again
                    </button>
                </div>
            )
        );
    }
}
