package main

import (
	"context"
	"sync/atomic"
	"testing"
	"time"
)

func TestLimiterCapsInFlight(t *testing.T) {
	const limit = 3
	const jobs = 8

	slots := newLimiter(limit)
	var current atomic.Int32
	var max atomic.Int32

	for i := 0; i < jobs; i++ {
		if err := slots.acquire(context.Background()); err != nil {
			t.Fatal(err)
		}

		slots.goWork(func() {
			n := current.Add(1)
			for {
				old := max.Load()
				if n <= old || max.CompareAndSwap(old, n) {
					break
				}
			}
			time.Sleep(40 * time.Millisecond)
			current.Add(-1)
		})
	}

	slots.wait()

	if max.Load() > limit {
		t.Fatalf("in-flight máximo = %d, limite = %d", max.Load(), limit)
	}
	if max.Load() != limit {
		t.Fatalf("in-flight máximo = %d, queria atingir o limite %d", max.Load(), limit)
	}
}

func TestLimiterAcquireCancels(t *testing.T) {
	slots := newLimiter(1)
	if err := slots.acquire(context.Background()); err != nil {
		t.Fatal(err)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Millisecond)
	defer cancel()

	err := slots.acquire(ctx)
	if err == nil {
		t.Fatal("acquire deveria cancelar com o contexto")
	}
}
