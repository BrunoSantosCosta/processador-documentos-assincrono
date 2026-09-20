package main

import (
	"context"
	"sync"
)

type limiter struct {
	sem chan struct{}
	wg  sync.WaitGroup
}

func newLimiter(n int) *limiter {
	return &limiter{sem: make(chan struct{}, n)}
}

func (l *limiter) acquire(ctx context.Context) error {
	select {
	case l.sem <- struct{}{}:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

func (l *limiter) release() {
	<-l.sem
}

func (l *limiter) goWork(fn func()) {
	l.wg.Add(1)
	go func() {
		defer l.wg.Done()
		defer l.release()
		fn()
	}()
}

func (l *limiter) wait() {
	l.wg.Wait()
}
